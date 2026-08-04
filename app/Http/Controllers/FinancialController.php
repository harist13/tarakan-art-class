<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinancialController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type')->toString();
        $month = self::resolveMonth($request);
        $search = $request->string('search')->toString();

        [$year, $mon] = $month !== '' ? explode('-', $month) : [null, null];
        $periodLabel = self::periodLabel($month);

        $query = Transaction::query()
            ->with(['recorder', 'payment'])
            ->when($year && $mon, fn ($q) => $q->whereYear('transaction_date', $year)->whereMonth('transaction_date', $mon))
            ->when(in_array($type, ['income', 'expense'], true), fn ($q) => $q->where('type', $type))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }));

        $transactions = (clone $query)->orderByDesc('transaction_date')->paginate(15)->withQueryString();

        $totalIncome = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        return view('financials.index', compact('transactions', 'totalIncome', 'totalExpense', 'balance', 'type', 'month', 'search', 'periodLabel'));
    }

    public function create()
    {
        return view('financials.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['recorded_by'] = auth()->id();

        DB::transaction(function () use ($data) {
            $transaction = Transaction::create($data);
            ActivityLog::record('created', $transaction, "Mencatat {$transaction->type} - {$transaction->category}");
        });

        return redirect()->route('financials.index')->with('success', 'Transaksi berhasil dicatat.');
    }

    public function edit(Transaction $financial)
    {
        if ($guard = $this->guardAutoTransaction($financial)) {
            return $guard;
        }

        return view('financials.edit', ['transaction' => $financial]);
    }

    public function update(Request $request, Transaction $financial)
    {
        if ($guard = $this->guardAutoTransaction($financial)) {
            return $guard;
        }

        $data = $this->validateData($request);

        DB::transaction(function () use ($financial, $data) {
            $financial->update($data);
            ActivityLog::record('updated', $financial, "Memperbarui transaksi {$financial->category}");
        });

        return redirect()->route('financials.index')->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Transaction $financial)
    {
        if ($guard = $this->guardAutoTransaction($financial)) {
            return $guard;
        }

        DB::transaction(function () use ($financial) {
            ActivityLog::record('deleted', $financial, "Menghapus transaksi {$financial->category}");
            $financial->delete();
        });

        return redirect()->route('financials.index')->with('success', 'Transaksi berhasil dihapus.');
    }

    /**
     * Aturan filter periode (dipakai bersama oleh halaman & export):
     * - parameter "month" tidak dikirim (kunjungan pertama) → default bulan berjalan;
     * - dikirim tapi kosong (filter dibersihkan / tombol "Semua Bulan") → semua periode;
     * - format selain YYYY-MM diperlakukan sebagai semua periode.
     */
    public static function resolveMonth(Request $request): string
    {
        if (! $request->has('month')) {
            return now()->format('Y-m');
        }

        $month = trim($request->string('month')->toString());

        return preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : '';
    }

    public static function periodLabel(string $month): string
    {
        if ($month === '') {
            return 'Semua Periode';
        }

        return Carbon::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('F Y');
    }

    /**
     * Transaksi hasil sinkronisasi pembayaran tidak boleh diubah/dihapus di sini,
     * supaya nominalnya selalu sama dengan invoice di menu Pembayaran.
     */
    private function guardAutoTransaction(Transaction $transaction): ?RedirectResponse
    {
        if (! $transaction->payment_id) {
            return null;
        }

        return redirect()->route('financials.index')
            ->with('error', 'Transaksi ini otomatis dari pembayaran lunas. Ubah atau void invoicenya di menu Pembayaran.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
