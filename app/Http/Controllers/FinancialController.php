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

        // Cakupan ringkasan: periode + pencarian, TANPA filter tipe. Kalau filter tipe
        // ikut dipakai di sini, kartu lawannya jadi 0 dan Saldo salah — mis. saat memilih
        // "Pemasukan", saldo akan sama dengan total pemasukan seolah tidak ada pengeluaran.
        $scope = Transaction::query()
            ->when($year && $mon, fn ($q) => $q->whereYear('transaction_date', $year)->whereMonth('transaction_date', $mon))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }));

        // Filter tipe hanya menyaring isi tabel, bukan ringkasannya.
        $transactions = (clone $scope)
            ->with(['recorder', 'payment', 'inventoryItem'])
            ->when(in_array($type, ['income', 'expense'], true), fn ($q) => $q->where('type', $type))
            // Tanggal transaksi cuma menyimpan tanggal (tanpa jam), jadi transaksi di hari
            // yang sama urutannya tidak menentu. id menurun dipakai sebagai pemecah supaya
            // catatan terbaru — manual maupun otomatis dari invoice/inventaris — selalu di atas.
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Ringkasan pemasukan/pengeluaran/saldo hanya untuk Super Admin — admin biasa
        // tetap boleh mencatat dan melihat rincian transaksi, tapi tidak totalnya.
        $canViewSummary = auth()->user()?->isSuperAdmin() ?? false;

        $totalIncome = $canViewSummary ? (float) (clone $scope)->where('type', 'income')->sum('amount') : 0.0;
        $totalExpense = $canViewSummary ? (float) (clone $scope)->where('type', 'expense')->sum('amount') : 0.0;
        $balance = $totalIncome - $totalExpense;

        return view('financials.index', compact('transactions', 'canViewSummary', 'totalIncome', 'totalExpense', 'balance', 'type', 'month', 'search', 'periodLabel'));
    }

    public function create()
    {
        return view('financials.create', ['categories' => $this->categoryOptions()]);
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
        if ($guard = $this->guardAutoTransaction($financial) ?? $this->guardRestrictedCategory($financial)) {
            return $guard;
        }

        return view('financials.edit', [
            'transaction' => $financial,
            'categories' => $this->categoryOptions($financial),
        ]);
    }

    public function update(Request $request, Transaction $financial)
    {
        if ($guard = $this->guardAutoTransaction($financial) ?? $this->guardRestrictedCategory($financial)) {
            return $guard;
        }

        $data = $this->validateData($request, $financial);

        DB::transaction(function () use ($financial, $data) {
            $financial->update($data);
            ActivityLog::record('updated', $financial, "Memperbarui transaksi {$financial->category}");
        });

        return redirect()->route('financials.index')->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Transaction $financial)
    {
        if ($guard = $this->guardAutoTransaction($financial) ?? $this->guardRestrictedCategory($financial)) {
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
     * - "month" diisi YYYY-MM → hanya bulan itu;
     * - tidak dikirim, kosong, atau formatnya tidak valid → seluruh periode (default).
     */
    public static function resolveMonth(Request $request): string
    {
        $month = trim($request->string('month')->toString());

        return preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : '';
    }

    public static function periodLabel(string $month): string
    {
        if ($month === '') {
            return 'Semua periode';
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

    /**
     * Daftar isi dropdown kategori. Kategori transaksi lama yang sudah tidak ada di
     * daftar tetap disertakan supaya nilainya tidak ikut berubah saat transaksi itu diedit.
     */
    private function categoryOptions(?Transaction $transaction = null): array
    {
        $categories = auth()->user()?->isSuperAdmin()
            ? Transaction::CATEGORIES
            : array_values(array_diff(Transaction::CATEGORIES, Transaction::SUPER_ADMIN_CATEGORIES));

        if ($transaction?->category && ! in_array($transaction->category, $categories, true)) {
            $categories[] = $transaction->category;
        }

        return $categories;
    }

    /**
     * Transaksi berkategori khusus Super Admin (mis. Gaji Tutor) tidak boleh
     * diubah atau dihapus admin biasa.
     */
    private function guardRestrictedCategory(Transaction $transaction): ?RedirectResponse
    {
        if (! in_array($transaction->category, Transaction::SUPER_ADMIN_CATEGORIES, true)) {
            return null;
        }

        if (auth()->user()?->isSuperAdmin()) {
            return null;
        }

        return redirect()->route('financials.index')
            ->with('error', "Transaksi kategori {$transaction->category} hanya bisa dikelola Super Admin.");
    }

    private function validateData(Request $request, ?Transaction $transaction = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', Rule::in($this->categoryOptions($transaction))],
            'amount' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
