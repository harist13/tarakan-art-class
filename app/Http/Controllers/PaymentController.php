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
use Illuminate\Validation\ValidationException;

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

    /**
     * Pratinjau penerbitan invoice — satu murid maupun sebulan penuh.
     *
     * Dulu ini dua halaman: "Buat Invoice" untuk satu murid dan "Tagihan
     * Bulanan" untuk banyak. Keduanya menghasilkan baris yang sama persis di
     * tabel payments, jadi yang dibedakan sebenarnya cuma banyaknya centang —
     * dan dua menu yang menghasilkan benda yang sama membuat admin harus
     * menebak harus klik yang mana.
     *
     * Sengaja dua langkah (pratinjau → simpan), bukan sekali klik atau cron
     * harian: biaya kelas bisa berubah, murid bisa masuk tengah bulan, dan
     * invoice salah yang terlanjur terbit harus di-void satu per satu.
     */
    public function create(Request $request)
    {
        // Tagihan lepas: biaya di luar SPP bulanan. Tanpa periode, aturan satu
        // invoice per murid per bulan tidak berlaku — memang harus begitu,
        // sebab tagihan semacam ini boleh berulang dalam bulan yang sama.
        $lepas = $request->boolean('lepas');
        $period = $lepas ? null : $this->resolvePeriod($request->string('period')->toString());

        if ($lepas) {
            // Tanpa periode tidak ada yang bisa dilewati: murid yang belum punya
            // kelas berbiaya justru kasus utamanya (biaya pendaftaran), dan murid
            // yang ditangguhkan tetap boleh ditagih hal di luar SPP. Jadi semua
            // murid aktif ditampilkan, dan tidak ada yang dicentang lebih dulu —
            // tagihan lepas selalu untuk beberapa orang tertentu, tidak pernah
            // "semuanya".
            $billable = Student::where('status', 'active')->with('classes')->orderBy('name')->get()
                ->map(fn (Student $s) => ['student' => $s, 'amount' => $s->billableFee()])
                ->all();

            return view('payments.create', [
                'period' => null,
                'lepas' => true,
                'preselect' => false,
                'billable' => $billable,
                'skipped' => [],
            ]);
        }

        $students = Student::where('status', 'active')
            ->with(['classes', 'payments' => fn ($q) => $q->forPeriod($period)])
            ->orderBy('name')
            ->get();

        $billable = [];
        $skipped = [];

        // Aturan siapa yang layak ditagih tinggal di Student::billingSkip()
        // supaya badge "Belum ditagih" di daftar murid menjawab pertanyaan yang
        // sama persis dengan halaman ini. Dua salinan aturan akan menyimpang,
        // dan badge yang menuduh admin melewatkan murid yang sebenarnya memang
        // sengaja tidak ditagih lebih buruk daripada tidak ada badge sama sekali.
        foreach ($students as $student) {
            // Alasan sekaligus nadanya (hijau/merah/netral) — keduanya datang
            // dari satu tempat, jadi Blade tidak perlu menebak warna dari teks.
            if ($skip = $student->billingSkip($period)) {
                $skipped[] = ['student' => $student] + $skip;

                continue;
            }

            $billable[] = ['student' => $student, 'amount' => $student->billableFee()];
        }

        return view('payments.create', [
            'period' => $period,
            'lepas' => false,
            'preselect' => true,
            'billable' => $billable,
            'skipped' => $skipped,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'billing_period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payment_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:payment_date'],
            'payment_method' => ['required', Rule::in(Payment::methodValues())],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'students' => ['required', 'array', 'min:1'],
            'students.*' => ['exists:students,id'],
            'amounts' => ['array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'students.required' => 'Tidak ada murid yang dicentang — tidak ada invoice yang bisa diterbitkan.',
            'billing_period.regex' => 'Periode tagihan harus berupa bulan yang sah, mis. 2026-08.',
        ]);

        $period = $data['billing_period'] ?? null;

        // Nominal kosong tidak boleh diam-diam jadi Rp 0: itu invoice yang
        // tampak lunas tanpa uang masuk. Muridnya disebut namanya supaya admin
        // tahu baris mana yang perlu diisi, bukan hanya "ada yang salah".
        $missing = collect($data['students'])
            ->reject(fn ($id) => is_numeric($data['amounts'][$id] ?? null))
            ->map(fn ($id) => Student::find($id)?->name)
            ->filter();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'amounts' => 'Nominal belum diisi untuk: '.$missing->implode(', ').'.',
            ]);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $period, &$created, &$skipped) {
            foreach ($data['students'] as $studentId) {
                // Diperiksa ulang di sini, bukan cukup di pratinjau: halaman
                // yang dibiarkan terbuka bisa ketinggalan invoice yang baru
                // dibuat admin lain, dan unique index akan menolaknya sebagai
                // error 500 alih-alih dilewati dengan tenang.
                if (Payment::existingForPeriod((int) $studentId, $period)) {
                    $skipped++;

                    continue;
                }

                // Bila status "paid", PaymentObserver otomatis mencatatnya sebagai
                // pemasukan di Financial Tracking (F7) / menu Laporan Keuangan.
                $payment = Payment::create([
                    'student_id' => $studentId,
                    'payment_date' => $data['payment_date'],
                    'due_date' => $data['due_date'],
                    'billing_period' => $period,
                    'payment_amount' => $data['amounts'][$studentId],
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $data['payment_status'],
                ]);

                ActivityLog::record('created', $payment,
                    "Menerbitkan invoice {$payment->invoice_number}".($period ? " periode {$period}" : ' (tagihan lepas)'));

                $created++;
            }
        });

        $label = $period ? 'periode '.Payment::labelForPeriod($period) : 'tagihan lepas';

        if ($created === 0) {
            return back()->with('error',
                "Tidak ada invoice baru untuk {$label} — semua murid yang dipilih sudah punya invoice periode itu.");
        }

        $message = "{$created} invoice {$label} berhasil diterbitkan.";

        if ($skipped > 0) {
            $message .= " {$skipped} murid dilewati karena sudah punya invoice periode itu.";
        }

        return redirect()->route('payments.index')->with('success', $message);
    }

    public function edit(Payment $payment)
    {
        $students = Student::with('classes')->orderBy('name')->get();

        return view('payments.edit', compact('payment', 'students'));
    }

    /**
     * Konfirmasi invoice tunai sebagai LUNAS.
     * Otomatis mencatat pemasukan ke Financial & Dashboard.
     */
    public function confirmPaid(Payment $payment)
    {
        if ($payment->payment_status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        // Tombolnya memang sudah dimatikan di layar, tapi itu hanya tampilan —
        // yang menahan pelunasan atas uang yang belum masuk adalah pemeriksaan
        // di sini. Channel gateway dilunasi oleh notifikasi Midtrans.
        if (! $payment->canConfirmManually()) {
            return back()->with('error',
                "Invoice {$payment->invoice_number} memakai {$payment->methodLabel()} — pelunasannya menunggu konfirmasi Midtrans, ".
                'bukan konfirmasi manual. Pakai tombol cek status; bila uangnya ternyata diterima tunai, '.
                'ubah dulu Metode / Channel-nya menjadi Cash lewat Edit.');
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

        // applyStatus() hanya menjawab "BARU saja lunas", jadi invoice yang sudah
        // dilunasi lebih dulu oleh webhook juga menjawab false. Keduanya tidak
        // boleh disamakan: halaman yang terbuka sebelum webhook tiba masih
        // menampilkan tombol ini, dan menekannya dulu berbuah pesan merah
        // "belum lunas" tepat di sebelah badge hijau Paid.
        if ($payment->payment_status === 'paid') {
            return back()->with('success',
                "Invoice {$payment->invoice_number} memang sudah LUNAS (status Midtrans: {$status['transaction_status']}).");
        }

        // Pengecekannya sendiri berhasil, tapi hasilnya "belum lunas" — dan itu
        // yang perlu dilihat admin. Ditandai merah supaya tidak terbaca sekilas
        // sebagai konfirmasi pembayaran seperti pesan sukses lainnya.
        return back()->with('error', "Status Midtrans: {$status['transaction_status']}. Invoice belum lunas.");
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $this->validateData($request);
        $this->guardDuplicatePeriod($data, $payment);

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

    /** Periode dari querystring; kembali ke bulan berjalan bila tidak masuk akal. */
    private function resolvePeriod(string $period): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) ? $period : Payment::periodFor();
    }

    /**
     * Tolak invoice kedua untuk murid & periode yang sama.
     *
     * Aturannya ada di database sebagai unique index; di sini hanya supaya
     * penolakannya berbentuk pesan yang bisa ditindaklanjuti.
     */
    private function guardDuplicatePeriod(array $data, ?Payment $except = null): void
    {
        $existing = Payment::existingForPeriod(
            (int) $data['student_id'],
            $data['billing_period'] ?? null,
            $except?->id
        );

        if ($existing === null) {
            return;
        }

        $status = $existing->payment_status === 'paid' ? 'sudah LUNAS' : 'masih Unpaid';

        throw ValidationException::withMessages([
            'billing_period' => "Murid ini sudah punya invoice {$existing->invoice_number} ({$status}) untuk periode ".
                Payment::labelForPeriod($data['billing_period']).'. Perbaiki invoice itu lewat Edit, '.
                'atau kosongkan Periode Tagihan bila ini memang tagihan lepas di luar SPP bulanan.',
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:payment_date'],
            'billing_period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(Payment::methodValues())],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'notes' => ['nullable', 'string'],
        ], [
            'billing_period.regex' => 'Periode tagihan harus berupa bulan yang sah, mis. 2026-08.',
        ]);
    }
}
