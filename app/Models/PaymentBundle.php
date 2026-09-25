<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tagihan gabungan: beberapa invoice satu keluarga dibayar sekali.
 *
 * Totalnya selalu dihitung dari invoice yang MASIH belum lunas, bukan
 * disimpan: invoice di dalamnya tetap bisa dibayar terpisah lewat tautannya
 * sendiri, dan orang tua tidak boleh ditagih dua kali untuk invoice itu.
 */
class PaymentBundle extends Model
{
    protected $fillable = ['created_by'];

    protected $casts = [
        'snap_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'snap_amount' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentBundle $bundle) {
            if (empty($bundle->code)) {
                $bundle->code = self::generateCode();
            }
            if (empty($bundle->pay_token)) {
                $bundle->pay_token = Str::random(32);
            }
        });
    }

    /** GAB-001, GAB-002, … */
    public static function generateCode(): string
    {
        $last = self::orderByDesc('id')->value('code');
        $next = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return 'GAB-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_bundle_items')
            ->orderBy('payments.id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return Collection<int, Payment> */
    public function unpaidPayments(): Collection
    {
        return $this->payments->where('payment_status', '!=', 'paid')->values();
    }

    /**
     * Total yang masih harus dibayar, dibulatkan per invoice ke rupiah penuh
     * — Midtrans menolak desimal, dan item_details harus berjumlah persis sama.
     */
    public function totalDue(): int
    {
        return (int) $this->unpaidPayments()->sum(fn (Payment $p) => (int) round((float) $p->payment_amount));
    }

    /** Lunas = tidak ada lagi invoice di dalamnya yang belum dibayar. */
    public function isPaid(): bool
    {
        return $this->payments->isNotEmpty() && $this->unpaidPayments()->isEmpty();
    }

    /** Masih menagih: belum lunas dan belum dibatalkan admin. */
    public function isOpen(): bool
    {
        return $this->cancelled_at === null && ! $this->isPaid();
    }

    public function awaitingGateway(): bool
    {
        return $this->isOpen() && $this->gateway_status === 'pending';
    }

    public function payUrl(): string
    {
        return route('pay.bundle.show', $this->pay_token);
    }

    /** "Alice & Tiffany", "Azka, Athar & Nadia". */
    public function studentNames(): string
    {
        $names = $this->payments->map(fn (Payment $p) => $p->student?->name)->filter()->unique()->values();

        return $names->count() > 1
            ? $names->slice(0, -1)->implode(', ').' & '.$names->last()
            : (string) $names->first();
    }

    /** Wali yang ditagih: murid pertama yang nomornya bisa dipakai. */
    public function guardian(): ?Student
    {
        $students = $this->payments->pluck('student')->filter();

        return $students->first(fn (Student $s) => $s->whatsappNumber() !== null) ?? $students->first();
    }
}
