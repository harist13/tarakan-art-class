<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinancialController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type')->toString();
        $month = $request->string('month')->toString() ?: now()->format('Y-m');
        $search = $request->string('search')->toString();

        [$year, $mon] = array_pad(explode('-', $month), 2, null);

        $query = Transaction::query()
            ->with('recorder')
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

        return view('financials.index', compact('transactions', 'totalIncome', 'totalExpense', 'balance', 'type', 'month', 'search'));
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
        return view('financials.edit', ['transaction' => $financial]);
    }

    public function update(Request $request, Transaction $financial)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($financial, $data) {
            $financial->update($data);
            ActivityLog::record('updated', $financial, "Memperbarui transaksi {$financial->category}");
        });

        return redirect()->route('financials.index')->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Transaction $financial)
    {
        DB::transaction(function () use ($financial) {
            ActivityLog::record('deleted', $financial, "Menghapus transaksi {$financial->category}");
            $financial->delete();
        });

        return redirect()->route('financials.index')->with('success', 'Transaksi berhasil dihapus.');
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
