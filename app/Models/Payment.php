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
        'billing_period',
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

    // ─── Periode tagihan ───────────────────────────────────────────
    //
    // billing_period ("YYYY-MM") menjawab "invoice ini untuk bulan apa",
    // terpisah dari payment_date yang hanya mencatat kapan invoice diterbitkan.
    // Kolomnya boleh kosong: itu berarti tagihan lepas di luar SPP bulanan
    // (biaya pendaftaran, pembelian alat) yang memang boleh berulang.

    /** Periode tagihan dari sebuah tanggal; tanpa argumen berarti bulan ini. */
    public static function periodFor($date = null): string
    {
        return Carbon::parse($date ?: now())->format('Y-m');
    }

    /** "2026-08" → "Agustus 2026". */
    public static function labelForPeriod(?string $period): string
    {
        if (empty($period)) {
            return 'tanpa periode';
        }

        return Carbon::createFromFormat('Y-m-d', $period.'-01')
            ->locale('id')
            ->translatedFormat('F Y');
    }

    /** Label periode invoice ini; null bila tagihan lepas. */
    public function periodLabel(): ?string
    {
        return empty($this->billing_period) ? null : self::labelForPeriod($this->billing_period);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('billing_period', $period);
    }

    /**
     * Invoice lain milik murid yang sama untuk periode yang sama, bila ada.
     *
     * Unique index di database sudah menolak duplikatnya, tapi penolakan itu
     * datang sebagai error SQL yang tidak bisa dibaca admin. Lewat sini
     * duplikatnya ditemukan lebih dulu sehingga pesannya bisa menyebut invoice
     * mana yang sudah ada dan apa statusnya.
     */
    public static function existingForPeriod(int $studentId, ?string $period, ?int $exceptId = null): ?self
    {
        if (empty($period)) {
            return null;
        }

        return self::where('student_id', $studentId)
            ->forPeriod($period)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();
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

    // ─── Metode / channel pembayaran ───────────────────────────────

    /**
     * Metode yang bisa dipilih admin, beserta labelnya.
     *
     *   kunci   → nilai yang tersimpan di payments.payment_method
     *   'form'  → label panjang untuk <select> di form pembuatan invoice
     *   'short' → label pendek untuk daftar, laporan, & pesan WhatsApp
     *
     * Satu-satunya tempat daftar ini ditulis. Sebelumnya ia tersalin di empat
     * tempat — dua <select> dan dua Rule::in — sehingga menambah satu metode
     * berarti harus mengingat keempatnya sekaligus, dan yang terlewat baru
     * ketahuan sebagai "pilihan ada di layar tapi ditolak saat disimpan".
     */
    public const METHODS = [
        'cash' => ['form' => 'Cash', 'short' => 'Cash'],
        'transfer' => ['form' => 'Transfer Bank', 'short' => 'Transfer'],
        'qris' => ['form' => 'QRIS / E-Wallet (GoPay, DANA, OVO, ShopeePay, dll.)', 'short' => 'QRIS / E-Wallet'],
        'virtual_account' => ['form' => 'Virtual Account', 'short' => 'Virtual Account'],
    ];

    /**
     * Nilai warisan → metode yang berlaku sekarang.
     *
     * 'ewallet' sempat dipisah dari 'qris'; migrasi sudah menormalkan datanya.
     * Peta ini jaring pengaman agar baris yang lolos tidak tampil sebagai
     * "Ewallet" di layar dan tidak diam-diam berubah jadi Cash saat invoice
     * lama dibuka lalu disimpan ulang.
     */
    public const LEGACY_METHODS = ['ewallet' => 'qris'];

    /** Nilai metode yang sah — dipakai Rule::in di validasi. */
    public static function methodValues(): array
    {
        return array_keys(self::METHODS);
    }

    /** Nilai → label panjang, untuk <select> di form. */
    public static function methodFormOptions(): array
    {
        return array_map(fn (array $method) => $method['form'], self::METHODS);
    }

    /** Metode yang berlaku untuk sebuah nilai, termasuk nilai warisan. */
    public static function normalizeMethod(?string $method): ?string
    {
        return self::LEGACY_METHODS[$method] ?? $method;
    }

    /** Label metode pembayaran untuk layar & pesan WhatsApp. */
    public function methodLabel(): string
    {
        $method = self::normalizeMethod($this->payment_method);

        return self::METHODS[$method]['short'] ?? ucfirst((string) $this->payment_method);
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
