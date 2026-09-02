<?php

namespace App\Support;

use App\Models\ClassRoom;
use App\Models\HolidayClass;
use App\Models\ReplacementRequest;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sumber event kalender jadwal: kelas reguler, Holiday Class, & replacement.
 *
 * Berdiri sendiri di luar controller karena kalendernya kini muncul di dua
 * tempat — halaman Kalender Jadwal dan panel di Manajemen Kelas. Dua salinan
 * penyusun event akan menyimpang pelan-pelan, dan kalender yang menampilkan
 * jadwal berbeda tergantung dari mana ia dibuka lebih buruk daripada tidak ada
 * kalender di salah satunya.
 */
class ScheduleCalendar
{
    /**
     * Rentang kalender: kelas adalah slot mingguan tanpa akhir, jadi kejadiannya
     * harus dibatasi. Sedikit ke belakang agar riwayat terdekat tetap terlihat.
     */
    public const PAST_DAYS = 45;

    public const FUTURE_DAYS = 120;

    /**
     * Seluruh event untuk FullCalendar.
     *
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        return [...$this->classEvents(), ...$this->holidayEvents(), ...$this->replacementEvents()];
    }

    /**
     * Murid yang masih ikut kelas & tidak menunggak, untuk mode "Cari kelas
     * pengganti" (filter slot per level murid).
     *
     * @return Collection<int, Student>
     */
    public function students(): Collection
    {
        return Student::attendable()
            ->settled()
            ->orderBy('name')
            ->get(['id', 'name', 'student_id', 'class_type']);
    }

    /**
     * Jadwal kelas reguler. Setiap slot mingguan direntangkan jadi satu event
     * per kejadian dalam rentang tampilan.
     *
     * Available = biru; penuh/ditutup/lewat = abu-abu.
     *
     * @return list<array<string, mixed>>
     */
    private function classEvents(): array
    {
        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->get();

        $from = Carbon::today()->subDays(self::PAST_DAYS);
        $to = Carbon::today()->addDays(self::FUTURE_DAYS);
        $events = [];

        foreach ($classes as $class) {
            $slotAvailable = $class->isAvailable();
            $av = $class->availability();

            foreach ($class->occurrencesBetween($from, $to) as $at) {
                // Sesi yang sudah lewat tidak bisa dipakai sebagai slot pengganti,
                // walau slot mingguannya sendiri masih available.
                $past = $at->isPast();
                $available = $slotAvailable && ! $past;
                $label = $past ? 'Sudah lewat' : $av['text'];
                $end = $class->occurrenceEndAt($at);

                $events[] = [
                    'title' => $class->class_category.($available ? '' : ' ('.$label.')'),
                    'start' => $at->format('Y-m-d\TH:i:s'),
                    // Slot lama yang jam selesainya belum diisi dibiarkan tanpa
                    // 'end': kalender menggambarkannya sebagai satu titik waktu,
                    // bukan rentang yang tak pernah ditetapkan siapa pun.
                    'end' => $end?->format('Y-m-d\TH:i:s'),
                    'color' => $available ? '#0EA5E9' : '#94A3B8',
                    'extendedProps' => [
                        'type' => 'Kelas Reguler',
                        'tutor' => $class->tutor->name ?? '-',
                        'category' => $class->class_category,
                        'cat' => $class->class_category, // nilai mentah untuk pencocokan level murid
                        'classId' => $class->id,
                        'code' => $class->class_code,
                        'schedule' => $class->scheduleLabel(),
                        'time' => $class->timeRangeLabel(),
                        'availability' => $label,
                        'available' => $available,
                        'past' => $past,
                        'occupancy' => $class->enrolledCount().' / '.$class->capacity,
                        'editUrl' => route('classes.edit', $class),
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * Holiday Class — sesi musiman saat libur sekolah. Fuchsia, satu-satunya
     * warna yang belum dipakai: biru kelas reguler, abu penuh/ditutup, amber
     * & hijau & merah replacement.
     *
     * Bukan slot kelas pengganti (sekali sesi & berbayar), jadi sengaja tanpa
     * extendedProps 'available' — mode "cari kelas pengganti" hanya
     * menampilkannya sebagai konteks, tidak bisa diajukan.
     *
     * @return list<array<string, mixed>>
     */
    private function holidayEvents(): array
    {
        $events = [];

        foreach (HolidayClass::orderBy('schedule')->get() as $session) {
            $events[] = [
                'title' => '🌞 '.$session->class_name,
                'start' => $session->schedule->format('Y-m-d\TH:i:s'),
                'color' => '#C026D3',
                'url' => route('holiday-classes.edit', $session),
                'extendedProps' => [
                    'type' => 'Holiday Class',
                    'linkLabel' => 'Kelola Holiday Class',
                    'occupancy' => $session->capacity.' kursi ditawarkan',
                    'note' => 'Biaya Rp '.number_format((float) $session->price, 0, ',', '.').' / peserta',
                    // hasPassed(), bukan schedule->isPast(): batasnya awal hari, sama
                    // seperti scopeUpcoming() yang dipakai website & badge di menu
                    // Holiday Class. Sesi yang sedang berlangsung harus tetap tampil
                    // sampai harinya berakhir, bukan hilang begitu jam mulai terlewat.
                    'past' => $session->hasPassed(),
                ],
            ];
        }

        return $events;
    }

    /**
     * Replacement class, diwarnai menurut status pengajuannya.
     *
     * @return list<array<string, mixed>>
     */
    private function replacementEvents(): array
    {
        $statusColors = ['pending' => '#F59E0B', 'approved' => '#10B981', 'rejected' => '#EF4444'];
        $events = [];

        foreach (ReplacementRequest::with(['student', 'classRoom', 'originClass'])->get() as $req) {
            $events[] = [
                'title' => 'Replacement: '.($req->student->name ?? '-'),
                'start' => $this->combineDateTime($req->replacement_date, $req->replacement_time),
                'color' => $statusColors[$req->request_status] ?? '#6B7280',
                'url' => route('schedules.edit', $req),
                'extendedProps' => [
                    'type' => 'Replacement Class',
                    'status' => ucfirst($req->request_status),
                    'originClass' => $req->originClass->class_category ?? '-',
                    'newClass' => $req->classRoom->class_category ?? '-',
                    'reason' => $req->reason ?: '-',
                    // Disembunyikan toggle "Hanya slot available" — lihat visibleEvents().
                    'past' => $req->isPast(),
                ],
            ];
        }

        return $events;
    }

    /**
     * Gabungkan tanggal (Carbon) + jam (string HH:MM:SS) jadi ISO string.
     */
    private function combineDateTime($date, ?string $time): string
    {
        $iso = $date->format('Y-m-d');

        return $time ? $iso.'T'.substr($time, 0, 8) : $iso;
    }
}
