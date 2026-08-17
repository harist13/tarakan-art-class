<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Student;
use App\Support\InvoiceWhatsApp;
use App\Support\MidtransSnap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $payments = Payment::query()
            ->with('student')
            ->when($search, fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")))
            ->when(in_array($status, ['paid', 'unpaid'], true), fn ($q) => $q->where('payment_status', $status))
            // "overdue" = belum dibayar DAN sudah lewat jatuh tempo; ini yang
            // sebenarnya perlu ditagih, bukan seluruh invoice unpaid.
            ->when($status === 'overdue', fn ($q) => $q->overdue())
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Menentukan tampil-tidaknya tombol "salin tautan bayar": tanpa kunci
        // Midtrans, tautannya tidak ada gunanya dikirim.
        $snap = app(MidtransSnap::class);
        $midtransActive = $snap->isConfigured();

        // Alamat lokal tidak mungkin dijangkau server Midtrans, jadi notifikasi
        // pelunasan tidak akan pernah tiba. Gejalanya membingungkan (pembayaran
        // berhasil tapi invoice tetap Unpaid), maka disebutkan langsung di layar.
        $webhookUnreachable = $midtransActive && ! $snap->webhookReachable();

        return view('payments.index', compact('payments', 'search', 'status', 'midtransActive', 'webhookUnreachable'));
    }

    public function create()
    {
        $students = Student::where('status', 'active')->with('classes')->orderBy('name')->get();

        return view('payments.create', compact('students'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            // Bila status "paid", PaymentObserver otomatis mencatatnya sebagai
            // pemasukan di Financial Tracking (F7) / menu Laporan Keuangan.
            $payment = Payment::create($data);

            ActivityLog::record('created', $payment, "Mencatat pembayaran {$payment->invoice_number}");
        });

        return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function edit(Payment $payment)
    {
        $students = Student::with('classes')->orderBy('name')->get();

        return view('payments.edit', compact('payment', 'students'));
    }

    /**
     * Konfirmasi invoice sebagai LUNAS (mensimulasikan konfirmasi payment gateway).
     * Otomatis mencatat pemasukan ke Financial & Dashboard.
     */
    public function confirmPaid(Payment $payment)
    {
        if ($payment->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['payment_status' => 'paid']);
            ActivityLog::record('updated', $payment, "Konfirmasi lunas {$payment->invoice_number}");
        });

        return back()->with('success', "Invoice {$payment->invoice_number} dikonfirmasi LUNAS & tercatat di keuangan.");
    }

    /**
     * Buka chat WhatsApp wali murid dengan rincian invoice sudah terisi.
     *
     * Sengaja lewat controller (bukan tautan wa.me langsung di Blade) supaya
     * pengirimannya tercatat di Log Aktivitas — admin bisa menelusuri invoice
     * mana yang sudah ditagih dan oleh siapa.
     */
    public function sendWhatsapp(Payment $payment, MidtransSnap $snap)
    {
        $payment->loadMissing('student');

        // Tautan pembayaran hanya disertakan untuk invoice yang belum lunas dan
        // hanya bila Midtrans dikonfigurasi. Transaksi Snap-nya sendiri baru
        // dibuat saat orang tua membuka tautannya — di sini tidak ada panggilan
        // API, jadi tombol WhatsApp tidak pernah gagal karena gateway.
        $payUrl = $snap->isConfigured() && $payment->payment_status !== 'paid'
            ? $payment->payUrl()
            : null;

        $link = InvoiceWhatsApp::link($payment, $payUrl);

        if ($link === null) {
            return back()->with('error', 'Nomor HP wali murid belum terisi atau tidak valid. Lengkapi dulu di menu Murid.');
        }

        ActivityLog::record('sent', $payment, "Mengirim invoice {$payment->invoice_number} via WhatsApp ke {$payment->student->name}");

        return redirect()->away($link);
    }

    /**
     * Tarik ulang status dari Midtrans untuk invoice yang menunggu pembayaran.
     *
     * Jaring pengaman bila webhook tidak sampai — misalnya server sedang mati
     * saat orang tua membayar, atau notifikasi belum dipasang di dashboard.
     */
    public function syncGateway(Payment $payment, MidtransSnap $snap)
    {
        if (! $snap->isConfigured() || ! $payment->snap_order_id) {
            return back()->with('error', 'Invoice ini belum punya transaksi Midtrans untuk dicek.');
        }

        $status = $snap->statusFor($payment);

        if (empty($status['transaction_status'])) {
            // Bedakan "Midtrans menjawab: tidak ada transaksinya" dari kegagalan
            // membaca. Pesan lama menyuruh admin mencoba lagi, padahal mengulang
            // tidak akan mengubah apa pun — yang perlu diperiksa hal lain.
            $reason = $status['status_message'] ?? 'Midtrans tidak menjawab.';

            return back()->with('error',
                "Midtrans belum mencatat pembayaran untuk order {$payment->snap_order_id} — \"{$reason}\" ".
                'Bila transaksinya terlihat di Dashboard Midtrans, berarti pencarian lewat order_id tidak menemukannya '.
                '(sering terjadi pada channel e-wallet). Pasang Payment Notification URL agar statusnya dikirim otomatis.');
        }

        $settled = DB::transaction(fn () => $snap->applyStatus($payment, $status));

        if ($settled) {
            ActivityLog::record('updated', $payment, "Invoice {$payment->invoice_number} lunas via Midtrans (cek manual)");

            return back()->with('success', "Invoice {$payment->invoice_number} sudah LUNAS & tercatat di keuangan.");
        }

        // Pengecekannya sendiri berhasil, tapi hasilnya "belum lunas" — dan itu
        // yang perlu dilihat admin. Ditandai merah supaya tidak terbaca sekilas
        // sebagai konfirmasi pembayaran seperti pesan sukses lainnya.
        return back()->with('error', "Status Midtrans: {$status['transaction_status']}. Invoice belum lunas.");
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($payment, $data) {
            // Observer menyinkronkan pemasukan: dibuat saat jadi "paid", nominal &
            // tanggalnya ikut terkoreksi saat invoice direvisi, dihapus saat "unpaid".
            $payment->update($data);

            ActivityLog::record('updated', $payment, "Memperbarui pembayaran {$payment->invoice_number}");
        });

        return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(Payment $payment)
    {
        // Void — hanya Super Admin (dijaga di route).
        // Observer ikut menghapus pemasukan otomatisnya dari Laporan Keuangan.
        DB::transaction(function () use ($payment) {
            ActivityLog::record('deleted', $payment, "Void pembayaran {$payment->invoice_number}");
            $payment->delete();
        });

        return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil dibatalkan (void).');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:payment_date'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'virtual_account'])],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
