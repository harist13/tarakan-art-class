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
     * Daftar isi tiap kelas: tutor, jam, dan murid aktifnya.
     *
     * Dikirim terpisah dari events, bukan ditempelkan ke tiap kejadian: satu slot
     * mingguan merentang jadi puluhan kejadian, dan menyalin seluruh daftar murid
     * ke masing-masingnya akan melipatgandakan muatan halaman tanpa menambah satu
     * pun keterangan baru. Penelusuran di layar menyambungkannya lewat classId.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rosters(): array
    {
        $classes = ClassRoom::with([
            'tutor',
            'students' => fn ($q) => $q->where('student_class.status', 'active')->orderBy('name'),
        ])->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->get();

        $rosters = [];

        foreach ($classes as $class) {
            $av = $class->availability();
            $next = $class->nextOccurrence();

            $rosters[$class->id] = [
                'id' => $class->id,
                'code' => $class->class_code,
                'category' => $class->class_category,
                'tutor' => $class->tutor->name ?? null,
                'tutorPhone' => $class->tutor->phone_number ?? null,
                'dayName' => $class->dayName(),
                'time' => $class->timeRangeLabel(),
                'schedule' => $class->scheduleLabel(),
                'capacity' => $class->capacity,
                'enrolled' => $class->students->count(),
                'availability' => $av['text'],
                // Dipakai daftar slot per kategori di layar Scheduler: status,
                // sesi terdekat, dan pintu ke pendaftaran anak semuanya turun
                // dari roster yang sama dengan yang menggerakkan kalender.
                'availabilityColor' => $av['color'],
                'available' => $class->isAvailable(),
                'closed' => $class->isClosed(),
                'remaining' => $class->remainingSeats(),
                'recurring' => (bool) $class->is_recurring,
                'nextSession' => $next?->format('d M Y'),
                'editUrl' => route('classes.edit', $class),
                // Mendaftarkan anak langsung ke slot ini — form murid menerima
                // class_id dan memakai slot itu apa adanya, bukan menebak sendiri
                // kelas mana dalam kategori yang kebetulan masih kosong.
                'enrollUrl' => route('students.create', ['class_id' => $class->id]),
                'toggleUrl' => route('classes.toggle-status', $class),
                'students' => $class->students->map(fn (Student $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'studentId' => $s->student_id,
                    'age' => $s->age,
                    'parent' => $s->parent_name,
                    'phone' => $s->phone_number,
                    'category' => $s->class_type,
                    'url' => route('students.edit', $s),
                ])->all(),
            ];
        }

        return $rosters;
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
        $guests = $this->approvedGuests();
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
                        // Murid titipan hari itu saja — replacement melekat pada satu
                        // tanggal, jadi tidak bisa ikut di roster kelas yang berlaku
                        // untuk seluruh pekan.
                        'guests' => $guests[$class->id][$at->toDateString()] ?? [],
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
     * Murid replacement yang disetujui, dikelompokkan per kelas & tanggal.
     *
     * Hanya yang approved: pengajuan pending belum tentu jadi, dan menampilkannya
     * sebagai peserta membuat tutor menyiapkan kursi untuk anak yang mungkin
     * tidak datang.
     *
     * @return array<int, array<string, list<array<string, mixed>>>>
     */
    private function approvedGuests(): array
    {
        $guests = [];

        $requests = ReplacementRequest::with('student')
            ->where('request_status', 'approved')
            ->get();

        foreach ($requests as $req) {
            if (! $req->class_id || ! $req->student) {
                continue;
            }

            $guests[$req->class_id][$req->replacement_date->toDateString()][] = [
                'id' => $req->student->id,
                'name' => $req->student->name,
                'studentId' => $req->student->student_id,
                'url' => route('students.edit', $req->student),
            ];
        }

        return $guests;
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
