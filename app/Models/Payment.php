<?php

namespace App\Models;

use App\Observers\PaymentObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public static function generateInvoiceNumber(): string
    {
        $last = self::orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', $last->invoice_number)) + 1 : 1;

        return 'INV'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
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
