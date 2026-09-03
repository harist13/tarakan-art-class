<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Tipe Transaksi</label>
        <select name="type" class="form-select" required>
            <option value="income" @selected(old('type', $transaction->type ?? '') === 'income')>Pemasukan</option>
            <option value="expense" @selected(old('type', $transaction->type ?? 'expense') === 'expense')>Pengeluaran</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Kategori</label>
        @php($selectedCategory = old('category', $transaction->category ?? ''))
        <select name="category" class="form-select" required>
            <option value="">-- Pilih Kategori --</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Jumlah (Rp)</label>
        <input type="number" step="1000" min="0" name="amount" class="form-control" value="{{ old('amount', $transaction->amount ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal</label>
        <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', isset($transaction) ? $transaction->transaction_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Deskripsi (opsional)</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $transaction->description ?? '') }}</textarea>
    </div>
</div>
