<?php

namespace App\Models;

use App\Observers\StudentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(StudentObserver::class)]
class Student extends Model
{
    protected $fillable = [
        'student_id',
        'name',
        'date_of_birth',
        'age',
        'parent_name',
        'phone_number',
        'instagram_username',
        'address',
        'class_type',
        'status',
        'join_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'join_date' => 'date',
        'suspended_at' => 'datetime',
        'age' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            if (empty($student->student_id)) {
                $student->student_id = self::generateStudentId();
            }
            if (empty($student->join_date)) {
                $student->join_date = now()->toDateString();
            }
        });
    }

    public static function generateStudentId(): string
    {
        $last = self::orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', $last->student_id)) + 1 : 1;

        return 'STD'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Usia murid (tahun penuh). Nilai manual di kolom `age` diprioritaskan;
     * bila kosong, dihitung otomatis dari tanggal lahir.
     */
    public function getAgeAttribute($value): ?int
    {
        return $value !== null ? (int) $value : $this->date_of_birth?->age;
    }

    /** Usia hasil hitung otomatis, mengabaikan nilai manual. */
    public function getCalculatedAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /** True bila usia diisi manual dan berbeda dari hitungan tanggal lahir. */
    public function getHasManualAgeAttribute(): bool
    {
        $manual = $this->attributes['age'] ?? null;

        return $manual !== null && (int) $manual !== $this->calculated_age;
    }

    /**
     * Nomor WhatsApp wali dalam format internasional tanpa "+" (dipakai wa.me).
     *
     * Kolom `phone_number` diisi bebas oleh admin — 0812…, 62812…, +62 812-…,
     * atau 812… yang angka nolnya terpotong saat disalin. Semua dinormalkan ke
     * 62…; null bila nomornya terlalu pendek untuk dipakai.
     */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone_number);

        $digits = match (true) {
            $digits === '' => '',
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            default => '62'.$digits,
        };

        return strlen($digits) >= 10 ? $digits : null;
    }

    // ─── Status pembayaran ─────────────────────────────────────────
    //
    // Data akademik murid TIDAK disembunyikan karena tagihan. Kelas ini tatap
    // muka: kalau anaknya datang, kehadirannya harus tetap bisa dicatat. Yang
    // digerbang uang hanyalah hal yang memang bisa ditahan tanpa merusak catatan
    // — kelas pengganti dan akses raport orang tua — plus penangguhan otomatis
    // setelah masa toleransi lewat. Lihat config/academic.php.

    /**
     * Murid menunggak: punya invoice yang lewat jatuh tempo dan belum dibayar.
     * Invoice yang baru terbit dan belum jatuh tempo TIDAK menghitung.
     */
    public function scopeInArrears(Builder $query): Builder
    {
        return $query->whereHas('payments', fn ($q) => $q->overdue());
    }

    /** Kebalikan scopeInArrears: tidak punya tagihan yang lewat jatuh tempo. */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereDoesntHave('payments', fn ($q) => $q->overdue());
    }

    /** Murid yang boleh masuk daftar kelas ke depan: aktif & tidak ditangguhkan. */
    public function scopeAttendable(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('suspended_at');
    }

    /** Invoice yang lewat jatuh tempo. Memakai relasi yang sudah dimuat bila ada. */
    public function arrears()
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->filter->isOverdue()->values();
        }

        return $this->payments()->overdue()->get();
    }

    public function hasArrears(): bool
    {
        return $this->arrears()->isNotEmpty();
    }

    /** Umur tunggakan terlama dalam hari; 0 bila tidak menunggak. */
    public function arrearsDays(): int
    {
        return (int) $this->arrears()->max(fn (Payment $p) => $p->daysOverdue()) ?: 0;
    }

    /** Sudah pernah membayar minimal satu invoice — pendaftarannya sudah aktif. */
    public function isActivated(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->contains('payment_status', 'paid');
        }

        return $this->payments()->where('payment_status', 'paid')->exists();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Ditangguhkan sistem: keluar dari daftar kelas ke depan, histori tetap utuh. */
    public function suspend(string $reason): void
    {
        $this->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();
    }

    public function unsuspend(): void
    {
        $this->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
    }

    /**
     * Alasan murid ditahan dari kelas pengganti & akses raport; null bila aman.
     * Dipakai di pesan validasi & catatan di layar agar admin paham penyebabnya.
     */
    public function paymentBlockReason(): ?string
    {
        $arrears = $this->arrears();

        if ($arrears->isEmpty()) {
            return null;
        }

        $days = (int) $arrears->max(fn (Payment $p) => $p->daysOverdue());

        return "punya {$arrears->count()} invoice lewat jatuh tempo (terlama {$days} hari)";
    }

    // ─── Kelayakan ditagih untuk sebuah periode ────────────────────
    //
    // Satu-satunya sumber kebenaran untuk pertanyaan "murid ini perlu ditagih
    // bulan ini atau tidak". Dipakai bersama oleh pratinjau Tagihan Bulanan dan
    // badge di daftar murid — kalau keduanya punya aturan sendiri-sendiri,
    // cepat atau lambat badge akan menuduh admin melewatkan murid yang memang
    // sengaja tidak ditagih, dan badge yang salah lebih buruk daripada tidak ada.

    /** Invoice murid ini untuk sebuah periode; memakai relasi yang sudah dimuat bila ada. */
    public function invoiceForPeriod(string $period): ?Payment
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->firstWhere('billing_period', $period);
        }

        return $this->payments()->forPeriod($period)->first();
    }

    /** Total biaya kelas yang diikuti — nominal bawaan satu invoice bulanan. */
    public function billableFee(): float
    {
        return (float) $this->classes->sum('class_fee');
    }

    /**
     * Alasan murid ini TIDAK ditagih untuk sebuah periode, beserta nada
     * tampilannya. null berarti sebaliknya: layak ditagih, invoicenya belum ada.
     *
     * `tone` ikut ditentukan di sini, bukan disimpulkan ulang di Blade dengan
     * mencocokkan kata "(Lunas)" pada kalimat alasannya. Menyimpulkan warna
     * dari teks berarti mengubah kalimat suatu hari nanti diam-diam mematikan
     * warnanya, dan warna yang salah pada angka uang lebih buruk daripada
     * tidak berwarna sama sekali.
     *
     *   paid    → sudah ditagih & uangnya sudah masuk
     *   unpaid  → sudah ditagih tapi belum dibayar
     *   neutral → bukan soal pembayaran (nonaktif, ditangguhkan, tanpa kelas)
     *
     * @return array{reason: string, tone: string}|null
     */
    public function billingSkip(string $period): ?array
    {
        if ($this->status !== 'active') {
            return ['reason' => 'murid tidak aktif', 'tone' => 'neutral'];
        }

        if ($invoice = $this->invoiceForPeriod($period)) {
            $lunas = $invoice->payment_status === 'paid';

            return [
                'reason' => "sudah ditagih lewat {$invoice->invoice_number} (".($lunas ? 'Lunas' : 'Unpaid').')',
                'tone' => $lunas ? 'paid' : 'unpaid',
            ];
        }

        // Murid yang ditangguhkan sudah dicabut dari daftar kelas ke depan, jadi
        // bulan ini ia tidak menerima pelajaran apa pun. Menagihnya hanya
        // memperdalam tunggakan atas jasa yang tidak diberikan.
        if ($this->isSuspended()) {
            return [
                'reason' => 'ditangguhkan karena tunggakan — di luar daftar kelas, jadi tidak ditagih',
                'tone' => 'neutral',
            ];
        }

        if ($this->billableFee() <= 0) {
            return ['reason' => 'belum terdaftar di kelas berbiaya', 'tone' => 'neutral'];
        }

        return null;
    }

    /** Alasannya saja, tanpa nada tampilan. */
    public function billingSkipReason(string $period): ?string
    {
        return $this->billingSkip($period)['reason'] ?? null;
    }

    /** Belum punya invoice untuk periode berjalan, padahal seharusnya ditagih. */
    public function isUnbilledThisPeriod(): bool
    {
        return $this->billingSkip(Payment::periodFor()) === null;
    }

    /**
     * Versi SQL dari billingSkipReason() === null, supaya daftar murid bisa
     * disaring & dihitung tanpa memuat seluruh tabel ke memori.
     *
     * Dua bentuk aturan yang sama memang berisiko menyimpang; itu dijaga tes
     * yang membandingkan hasil scope ini dengan billingSkipReason() satu per
     * satu. "Punya kelas berbiaya" di sini berbentuk "ada satu kelas dengan
     * class_fee > 0" — setara dengan jumlah biaya > 0 karena biaya kelas tidak
     * pernah negatif (divalidasi min:0).
     */
    public function scopeUnbilledFor(Builder $query, ?string $period = null): Builder
    {
        $period ??= Payment::periodFor();

        return $query->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereDoesntHave('payments', fn ($q) => $q->forPeriod($period))
            ->whereHas('classes', fn ($q) => $q->where('class_fee', '>', 0));
    }

    /**
     * Penanda tagihan di daftar murid: label, warna, dan penjelasannya.
     * null bila tidak ada yang perlu ditandai.
     *
     * Urutannya menurut apa yang paling perlu ditindaklanjuti lebih dulu.
     * "Belum ditagih" berada di atas "Belum bayar tagihan" karena lebih
     * spesifik dan lebih jujur: tidak masuk akal menagih pembayaran atas
     * invoice yang memang belum pernah diterbitkan.
     */
    public function paymentBadge(): ?array
    {
        if ($this->hasArrears()) {
            return [
                'label' => 'Menunggak '.$this->arrearsDays().' hari',
                'title' => 'Murid '.$this->paymentBlockReason().' — kelas pengganti & akses raport orang tua ditahan.',
                'class' => 'rounded-pill px-3 py-1 text-white fw-semibold',
                'style' => 'background-color: rgba(245, 136, 12, 1);',
                'icon' => 'bi-exclamation-triangle',
            ];
        }

        // Di awal bulan badge ini wajar muncul di hampir semua murid — memang
        // belum ada yang ditagih. Justru itu gunanya: ia hilang satu per satu
        // seiring invoice terbit, dan yang tersisa di akhir bulan adalah murid
        // yang benar-benar terlewat. Warnanya biru, bukan kuning: ini pekerjaan
        // admin yang belum selesai, bukan kesalahan murid.
        if ($this->isUnbilledThisPeriod()) {
            return [
                'label' => 'Belum ditagih '.Payment::labelForPeriod(Payment::periodFor()),
                'title' => 'Belum ada invoice untuk periode ini. Terbitkan lewat tombol Tagihan Bulanan di menu '.
                    'Pembayaran. Tanpa invoice tidak ada yang bisa ditagih, dan murid ini tidak akan pernah '.
                    'terhitung menunggak meski belum membayar.',
                'class' => 'rounded-pill px-3 py-1 text-white fw-semibold',
                'style' => 'background-color: #0891B2;',
                'icon' => 'bi-receipt',
            ];
        }

        if (! $this->isActivated()) {
            return [
                'label' => 'Belum bayar tagihan',
                'title' => 'Belum ada invoice yang dilunasi. Absensi tetap bisa dicatat.',
                'class' => 'rounded-pill px-3 py-1 text-white fw-semibold',
                'style' => 'background-color: rgba(245, 136, 12, 1);',
                'icon' => 'bi-exclamation-triangle',
            ];
        }

        return null;
    }

    /** Label pendek untuk badge di daftar murid; null bila tidak perlu ditandai. */
    public function paymentBadgeLabel(): ?string
    {
        return $this->paymentBadge()['label'] ?? null;
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'student_class', 'student_id', 'class_id')
            ->withPivot(['status', 'enrolled_at'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function replacementRequests(): HasMany
    {
        return $this->hasMany(ReplacementRequest::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(StudentReport::class);
    }
}
