<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    /**
     * Data contoh untuk demo — aman dijalankan berulang (idempotent-ish).
     */
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();
        $recordedBy = $admin?->id;

        // Tutors
        $tutors = collect([
            ['name' => 'Kak Rina', 'phone_number' => '081200000001', 'status' => 'active'],
            ['name' => 'Kak Bagus', 'phone_number' => '081200000002', 'status' => 'active'],
            ['name' => 'Kak Sari', 'phone_number' => '081200000003', 'status' => 'active'],
        ])->map(fn ($t) => Tutor::firstOrCreate(['name' => $t['name']], $t));

        // Classes
        $classDefs = [
            ['class_category' => 'preschool', 'capacity' => 10, 'class_fee' => 350000],
            ['class_category' => 'coloring', 'capacity' => 12, 'class_fee' => 300000],
            ['class_category' => 'drawing', 'capacity' => 8, 'class_fee' => 450000],
        ];
        $classes = collect($classDefs)->map(function ($c, $i) use ($tutors) {
            return ClassRoom::firstOrCreate(
                ['class_category' => $c['class_category']],
                array_merge($c, [
                    'tutor_id' => $tutors[$i % $tutors->count()]->id,
                    // Kelas mingguan: Senin, Selasa, Rabu terdekat.
                    'schedule_date' => now()->next($i + 1)->toDateString(),
                    'schedule_time' => sprintf('%02d:00:00', 9 + $i),
                ])
            );
        });

        // Students (spread join_date across last 6 months for growth chart)
        $names = ['Ahmad Dhani', 'Bella Safira', 'Citra Kirana', 'Doni Saputra', 'Elang Pratama',
            'Farah Nabila', 'Galih Wibowo', 'Hana Malika', 'Indra Lesmana', 'Jasmine Aulia'];

        foreach ($names as $i => $name) {
            $student = Student::firstOrCreate(
                ['name' => $name],
                [
                    'date_of_birth' => now()->subYears(7 + ($i % 5))->toDateString(),
                    'parent_name' => 'Orang Tua '.explode(' ', $name)[0],
                    'phone_number' => '08213456'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'class_type' => ['preschool', 'coloring', 'drawing'][$i % 3],
                    'status' => $i % 7 === 0 ? 'inactive' : 'active',
                    'join_date' => now()->subMonths(5 - min($i % 6, 5))->toDateString(),
                ]
            );

            // Enroll into a class
            $class = $classes[$i % $classes->count()];
            $student->classes()->syncWithoutDetaching([
                $class->id => ['status' => 'active', 'enrolled_at' => $student->join_date],
            ]);

            // A payment for some students
            if ($i % 2 === 0) {
                $paid = $i % 4 === 0 ? 'paid' : 'unpaid';
                $payment = Payment::firstOrCreate(
                    ['student_id' => $student->id, 'payment_date' => now()->startOfMonth()->toDateString()],
                    [
                        'payment_amount' => $class->class_fee,
                        'payment_method' => $i % 3 === 0 ? 'transfer' : 'cash',
                        'payment_status' => $paid,
                        'notes' => 'SPP bulan ini',
                    ]
                );

                if ($paid === 'paid' && ! Transaction::where('payment_id', $payment->id)->exists()) {
                    Transaction::create([
                        'type' => 'income',
                        'category' => 'SPP / Pembayaran Kelas',
                        'amount' => $payment->payment_amount,
                        'transaction_date' => $payment->payment_date,
                        'description' => "Pembayaran {$payment->invoice_number}",
                        'payment_id' => $payment->id,
                        'recorded_by' => $recordedBy,
                    ]);
                }
            }
        }

        // Some operational expenses
        $expenses = [
            ['category' => 'Gaji Tutor', 'amount' => 3000000],
            ['category' => 'Perlengkapan', 'amount' => 750000],
            ['category' => 'Operasional', 'amount' => 2000000],
        ];
        foreach ($expenses as $exp) {
            Transaction::firstOrCreate(
                ['category' => $exp['category'], 'transaction_date' => now()->startOfMonth()->toDateString(), 'type' => 'expense'],
                ['amount' => $exp['amount'], 'description' => 'Pengeluaran bulanan', 'recorded_by' => $recordedBy]
            );
        }
    }
}
