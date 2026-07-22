<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'invoice_number',
        'payment_date',
        'payment_amount',
        'payment_method',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->invoice_number)) {
                $payment->invoice_number = self::generateInvoiceNumber();
            }
        });
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
}
