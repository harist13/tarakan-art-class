<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Mengelompokkan invoice belum lunas per keluarga, untuk tagihan gabungan.
 *
 * Belum ada data "keluarga" di aplikasi ini, jadi keluarga ditebak dari data
 * wali: dua murid satu keluarga bila nomor WhatsApp walinya sama ATAU nama
 * walinya sama. Keterhubungannya menular — A & B senomor, B & C senama, maka
 * ketiganya satu kelompok — karena ayah dan ibu sering mendaftarkan anak
 * yang berbeda dengan nomornya masing-masing.
 *
 * Kecocokan nama saja lebih lemah dari nomor ("Ibu Ani" bisa dua orang), jadi
 * kelompok yang hanya tersambung lewat nama ditandai; admin yang memutuskan
 * lewat centang invoice mana yang benar-benar digabung.
 */
class GuardianGroups
{
    /** Sapaan yang dibuang sebelum nama wali dibandingkan. */
    private const HONORIFICS = ['ibu', 'bu', 'bapak', 'pak', 'bpk', 'mama', 'mami', 'mom', 'papa', 'papi', 'ayah', 'bunda'];

    /**
     * Kelompok keluarga yang punya minimal dua invoice belum lunas.
     *
     * @return Collection<int, array{key: string, guardian: string, students: Collection<int, Student>, payments: Collection<int, Payment>, name_only: bool}>
     */
    public static function unpaid(): Collection
    {
        $payments = Payment::query()
            ->with(['student', 'bundles'])
            ->where('payment_status', '!=', 'paid')
            ->whereHas('student')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $students = $payments->pluck('student')->unique('id')->values();

        return self::group($students)
            ->map(function (array $group) use ($payments) {
                $ids = $group['students']->pluck('id');

                return $group + [
                    'payments' => $payments->whereIn('student_id', $ids)->values(),
                ];
            })
            ->filter(fn (array $group) => $group['payments']->count() >= 2)
            ->sortBy('guardian', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Kunci kelompok tiap invoice — dipakai memastikan invoice yang dipilih
     * admin benar-benar satu keluarga.
     *
     * @return array<int, string> payment_id → kunci kelompok
     */
    public static function keyByPayment(): array
    {
        $map = [];

        foreach (self::unpaid() as $group) {
            foreach ($group['payments'] as $payment) {
                $map[$payment->id] = $group['key'];
            }
        }

        return $map;
    }

    /**
     * Union-find atas murid: disatukan lewat nomor WA, lalu lewat nama wali.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array{key: string, guardian: string, students: Collection<int, Student>, name_only: bool}>
     */
    private static function group(Collection $students): Collection
    {
        $parent = [];
        $find = function (int $id) use (&$parent, &$find): int {
            return $parent[$id] === $id ? $id : ($parent[$id] = $find($parent[$id]));
        };
        $union = function (int $a, int $b) use (&$parent, $find): void {
            $parent[$find($a)] = $find($b);
        };

        foreach ($students as $s) {
            $parent[$s->id] = $s->id;
        }

        // Pasangan yang tersambung lewat nomor, untuk membedakan kelompok yang
        // hanya bersandar pada kecocokan nama.
        $byPhone = $students->groupBy(fn (Student $s) => $s->whatsappNumber() ?? '')->forget('');
        foreach ($byPhone as $members) {
            $members->each(fn (Student $s) => $union($s->id, $members->first()->id));
        }
        $phoneRoot = [];
        foreach ($students as $s) {
            $phoneRoot[$s->id] = $find($s->id);
        }

        $byName = $students->groupBy(fn (Student $s) => self::normalizeName($s->parent_name))->forget('');
        foreach ($byName as $members) {
            $members->each(fn (Student $s) => $union($s->id, $members->first()->id));
        }

        return $students
            ->groupBy(fn (Student $s) => $find($s->id))
            ->map(fn (Collection $members, int $root) => [
                'key' => 'g'.$members->min('id'),
                'guardian' => $members->pluck('parent_name')->filter()->first() ?: $members->first()->name,
                'students' => $members->sortBy('name')->values(),
                // Lebih dari satu kelompok nomor di dalamnya = ada sambungan
                // yang hanya berasal dari nama.
                'name_only' => $members->map(fn (Student $s) => $phoneRoot[$s->id])->unique()->count() > 1,
            ])
            ->values();
    }

    /** "Ibu  Ani Wijaya" → "ani wijaya"; nama terlalu pendek diabaikan. */
    public static function normalizeName(?string $name): string
    {
        $name = mb_strtolower(trim(preg_replace('/[^\pL\s]+/u', ' ', (string) $name)));
        $words = array_values(array_filter(preg_split('/\s+/', $name)));

        while (count($words) > 1 && in_array($words[0], self::HONORIFICS, true)) {
            array_shift($words);
        }

        $normalized = implode(' ', $words);

        return mb_strlen($normalized) >= 3 ? $normalized : '';
    }
}
