<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Transaction;
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
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('payments.index', compact('payments', 'search', 'status'));
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
            $payment = Payment::create($data);

            // Jika lunas, otomatis catat sebagai pemasukan di Financial Tracking (F7).
            if ($payment->payment_status === 'paid') {
                $this->recordIncome($payment);
            }

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
            $this->recordIncome($payment);
            ActivityLog::record('updated', $payment, "Konfirmasi lunas {$payment->invoice_number}");
        });

        return back()->with('success', "Invoice {$payment->invoice_number} dikonfirmasi LUNAS & tercatat di keuangan.");
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($payment, $data) {
            $wasPaid = $payment->payment_status === 'paid';
            $payment->update($data);

            // Sinkronkan pemasukan otomatis mengikuti status pembayaran.
            if (! $wasPaid && $payment->payment_status === 'paid') {
                $this->recordIncome($payment);
            } elseif ($wasPaid && $payment->payment_status !== 'paid') {
                Transaction::where('payment_id', $payment->id)->delete();
            }

            ActivityLog::record('updated', $payment, "Memperbarui pembayaran {$payment->invoice_number}");
        });

        return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(Payment $payment)
    {
        // Void — hanya Super Admin (dijaga di route).
        DB::transaction(function () use ($payment) {
            Transaction::where('payment_id', $payment->id)->delete();
            ActivityLog::record('deleted', $payment, "Void pembayaran {$payment->invoice_number}");
            $payment->delete();
        });

        return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil dibatalkan (void).');
    }

    private function recordIncome(Payment $payment): void
    {
        Transaction::create([
            'type' => 'income',
            'category' => 'SPP / Pembayaran Kelas',
            'amount' => $payment->payment_amount,
            'transaction_date' => $payment->payment_date,
            'description' => "Pembayaran {$payment->invoice_number}",
            'payment_id' => $payment->id,
            'recorded_by' => auth()->id(),
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'virtual_account'])],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
