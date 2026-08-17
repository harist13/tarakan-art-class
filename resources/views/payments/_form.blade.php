<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" id="paymentStudent" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}"
                        data-fee="{{ $student->classes->sum('class_fee') }}"
                        @selected(old('student_id', $payment->student_id ?? '') == $student->id)>
                    {{ $student->name }} ({{ $student->student_id }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">Jumlah invoice terisi otomatis dari biaya kelas murid.</small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Tanggal Invoice</label>
        <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', isset($payment) ? $payment->payment_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Jatuh Tempo</label>
        <input type="date" name="due_date" class="form-control"
               value="{{ old('due_date', isset($payment) && $payment->due_date ? $payment->due_date->format('Y-m-d') : \App\Models\Payment::defaultDueDate()) }}">
        <small class="text-muted">Tunggakan baru dihitung setelah tanggal ini lewat.</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Jumlah Invoice (Rp)</label>
        <input type="number" step="1000" min="0" name="payment_amount" id="paymentAmount" class="form-control" value="{{ old('payment_amount', $payment->payment_amount ?? '') }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Metode / Channel</label>
        <select name="payment_method" class="form-select" required>
            <optgroup label="Manual">
                <option value="cash" @selected(old('payment_method', $payment->payment_method ?? '') === 'cash')>Cash</option>
                <option value="transfer" @selected(old('payment_method', $payment->payment_method ?? '') === 'transfer')>Transfer Bank</option>
            </optgroup>
            <optgroup label="Payment Gateway">
                <option value="qris" @selected(old('payment_method', $payment->payment_method ?? '') === 'qris')>QRIS</option>
                <option value="virtual_account" @selected(old('payment_method', $payment->payment_method ?? '') === 'virtual_account')>Virtual Account</option>
            </optgroup>
        </select>
        {{-- Penegasan penting: pilihan di sini TIDAK membatasi channel di Snap.
             Orang tua tetap bebas memilih apa pun di popup, dan nilai ini akan
             tertimpa channel sebenarnya begitu pembayaran online berhasil. --}}
        <small class="text-muted">Catatan awal admin. Tidak membatasi pilihan orang tua di halaman bayar.</small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Status</label>
        <select name="payment_status" class="form-select" required>
            <option value="unpaid" @selected(old('payment_status', $payment->payment_status ?? 'unpaid') === 'unpaid')>Unpaid (Invoice diterbitkan)</option>
            <option value="paid" @selected(old('payment_status', $payment->payment_status ?? '') === 'paid')>Paid (Lunas)</option>
        </select>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Catatan (opsional)</label>
        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $payment->notes ?? '') }}</textarea>
    </div>
</div>
<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Invoice <strong>Paid</strong> otomatis tercatat sebagai pemasukan di Laporan Keuangan &amp; Dashboard.
    Invoice <strong>Unpaid</strong> bisa dikirim ke WhatsApp wali murid lewat tombol <i class="bi bi-whatsapp"></i> di daftar
    pembayaran; pesannya membawa tautan bayar Midtrans, dan status invoice berubah lunas sendiri setelah pembayaran berhasil.
    Kolom <strong>Metode / Channel</strong> di atas hanya catatan awal — channel sebenarnya diisi otomatis dari gateway.
    <br>
    Invoice <strong>Unpaid</strong> yang belum jatuh tempo tidak menghalangi apa pun. Setelah lewat jatuh tempo, murid ditandai
    menunggak (kelas pengganti &amp; akses raport orang tua ditahan), dan setelah lewat
    <strong>{{ config('academic.payment.grace_days') }} hari</strong> masa toleransi murid ditangguhkan otomatis dari daftar kelas.
    Absensi tidak pernah ikut terkunci.
</div>

@push('scripts')
<script>
    const studentSelect = document.getElementById('paymentStudent');
    const amountInput = document.getElementById('paymentAmount');
    // Jumlah invoice selalu mengikuti murid yang sedang dipilih. Angka yang
    // diketik admin berlaku untuk murid itu saja — begitu muridnya diganti,
    // angka lama tidak lagi relevan dan diisi ulang dari biaya kelas murid baru.
    // Murid tanpa kelas berbiaya dikosongkan, bukan mewarisi nominal murid
    // sebelumnya; admin harus mengisinya sendiri secara sadar.
    studentSelect?.addEventListener('change', function () {
        const fee = Number(this.options[this.selectedIndex]?.dataset.fee || 0);
        amountInput.value = fee > 0 ? fee : '';
    });
</script>
@endpush
