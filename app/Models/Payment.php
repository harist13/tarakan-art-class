<?php

namespace App\Models;

use App\Observers\PaymentObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[ObservedBy(PaymentObserver::class)]
class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'invoice_number',
        'payment_date',
        'due_date',
        'payment_amount',
        'payment_method',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'due_date' => 'date',
        'payment_amount' => 'decimal:2',
        'snap_expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->invoice_number)) {
                $payment->invoice_number = self::generateInvoiceNumber();
            }
            if (empty($payment->due_date)) {
                $payment->due_date = self::defaultDueDate($payment->payment_date);
            }
            if (empty($payment->pay_token)) {
                $payment->pay_token = Str::random(32);
            }
        });

        // Nominal invoice direvisi → transaksi Snap yang lama tidak sah lagi.
        // Tokennya dibuang supaya tautan /bayar membuatkan transaksi baru dengan
        // order_id berikutnya, bukan menagih jumlah yang sudah tidak berlaku.
        static::updating(function (Payment $payment) {
            if ($payment->isDirty('payment_amount')) {
                $payment->snap_token = null;
                $payment->snap_redirect_url = null;
                $payment->snap_expires_at = null;
            }
        });
    }

    /** Jatuh tempo bawaan: tanggal invoice + academic.payment.due_days. */
    public static function defaultDueDate($paymentDate = null): string
    {
        return Carbon::parse($paymentDate ?: now())
            ->addDays((int) config('academic.payment.due_days', 7))
            ->toDateString();
    }

    // ─── Status jatuh tempo ────────────────────────────────────────

    /** Invoice yang masih harus dibayar. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * Invoice yang belum dibayar DAN sudah lewat jatuh tempo.
     * Invoice yang baru terbit (belum jatuh tempo) sengaja tidak termasuk —
     * itu bukan tunggakan, hanya tagihan yang sedang berjalan.
     */
    public function scopeOverdue(Builder $query, ?int $graceDays = null): Builder
    {
        $graceDays ??= 0;

        return $query->outstanding()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->subDays($graceDays)->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'unpaid'
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    /** Berapa hari lewat jatuh tempo; 0 bila lunas atau belum jatuh tempo. */
    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(today());
    }

    // ─── Tautan pembayaran (Midtrans Snap) ────────────────────────

    /**
     * Kunci tautan publik /bayar/{token}, dibuat sekali lalu dipakai selamanya.
     * Acak 32 karakter supaya tidak bisa ditebak dari nomor invoice.
     */
    public function payToken(): string
    {
        if (empty($this->pay_token)) {
            $this->forceFill(['pay_token' => Str::random(32)])->save();
        }

        return $this->pay_token;
    }

    /** Tautan yang dikirim ke orang tua lewat WhatsApp. */
    public function payUrl(): string
    {
        return route('pay.show', $this->payToken());
    }

    /** Sudah ada transaksi Snap berjalan yang menunggu dibayar. */
    public function awaitingGateway(): bool
    {
        return $this->payment_status !== 'paid' && $this->gateway_status === 'pending';
    }

    /**
     * Boleh dilunasi manual oleh admin lewat tombol "Lunas"?
     *
     * Hanya pembayaran tunai. Uang cash diterima admin di tempat, jadi dialah
     * satu-satunya yang bisa memastikannya. Channel lain diselesaikan sendiri
     * oleh Midtrans lewat notifikasi — menekan "Lunas" di situ berarti mencatat
     * pemasukan atas uang yang belum tentu masuk, dan invoice yang terlanjur
     * ditandai lunas tidak akan pernah dicek ulang ke gateway.
     */
    public function canConfirmManually(): bool
    {
        return $this->payment_status !== 'paid' && $this->payment_method === 'cash';
    }

    /** Label metode pembayaran untuk layar & pesan WhatsApp. */
    public function methodLabel(): string
    {
        return [
            'cash' => 'Cash',
            'transfer' => 'Transfer',
            'qris' => 'QRIS / E-Wallet',
            // Nilai peninggalan saat QRIS & e-wallet sempat dipisah. Migrasi
            // sudah menormalkannya ke 'qris'; baris ini jaring pengaman agar
            // data yang lolos tidak tampil sebagai "Ewallet".
            'ewallet' => 'QRIS / E-Wallet',
            'virtual_account' => 'Virtual Account',
        ][$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    /**
     * Nomor invoice diambil dari pencacah tersendiri, bukan dari baris terakhir
     * tabel ini. Dengan begitu menghapus invoice terbaru tidak membebaskan
     * nomornya untuk dipakai ulang — riwayat pembukuan tetap runut dan
     * order_id Midtrans tidak pernah bertabrakan.
     *
     * Nomor bisa melompat bila ada invoice yang dihapus; itu memang disengaja,
     * sama seperti nomor faktur pada umumnya.
     */
    public static function generateInvoiceNumber(): string
    {
        return 'INV'.str_pad((string) NumberSequence::next('invoice'), 3, '0', STR_PAD_LEFT);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Transaksi pemasukan otomatis di Laporan Keuangan (dibuat oleh PaymentObserver).
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
