<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentReport extends Model
{
    protected $fillable = [
        'student_id',
        'period_start',
        'period_end',
        'activity_notes',
        'tutor_notes',
        'progress_first_photo',
        'progress_first_caption',
        'progress_last_photo',
        'progress_last_caption',
        'credential_key',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (StudentReport $report) {
            if (empty($report->credential_key)) {
                $report->credential_key = self::generateCredentialKey();
            }
        });
    }

    public static function generateCredentialKey(): string
    {
        $prefix = 'TAC-';
        $last = self::where('credential_key', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();
        $next = $last ? ((int) preg_replace('/\D/', '', substr($last->credential_key, strlen($prefix)))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Foto karya murid sepanjang periode raport ini.
     *
     * Sengaja bukan relasi foreign key: karya terikat pada murid + tanggal, bukan
     * pada satu raport. Yang menyatukannya adalah rentang periode — jadi karya
     * yang diunggah sebelum raportnya dibuat pun tetap ikut terbawa.
     *
     * @return Builder<Artwork>
     */
    public function artworkQuery(): Builder
    {
        return Artwork::where('student_id', $this->student_id)
            ->inPeriod($this->period_start, $this->period_end)
            ->orderBy('taken_on')
            ->orderBy('id');
    }

    /**
     * Slot foto progres: kunci form → label yang dilihat orang tua.
     * "Terakhir", bukan "keempat": ada bulan yang punya lima minggu.
     */
    public const PROGRESS_SLOTS = [
        'first' => 'Minggu pertama',
        'last' => 'Minggu terakhir',
    ];

    /**
     * Foto progres yang terisi, berurutan awal → akhir.
     *
     * @return list<array{label: string, url: string, caption: ?string}>
     */
    public function progressPhotos(): array
    {
        $photos = [];

        foreach (self::PROGRESS_SLOTS as $slot => $label) {
            $path = $this->{"progress_{$slot}_photo"};

            if ($path) {
                $photos[] = [
                    'label' => $label,
                    'url' => asset('storage/'.$path),
                    'caption' => $this->{"progress_{$slot}_caption"},
                ];
            }
        }

        return $photos;
    }

    /** @return list<string> berkas foto progres yang tersimpan di disk public. */
    public function progressPhotoPaths(): array
    {
        return array_values(array_filter([$this->progress_first_photo, $this->progress_last_photo]));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
