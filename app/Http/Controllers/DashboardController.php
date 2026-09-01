<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\Transaction;

class DashboardController extends Controller
{
    public function index()
    {
        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'active')->count();
        $inactiveStudents = $totalStudents - $activeStudents;
        $totalClasses = ClassRoom::count();

        $totalIncome = Transaction::where('type', 'income')->sum('amount');

        $monthIncome = Transaction::where('type', 'income')
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $monthExpense = Transaction::where('type', 'expense')
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $unpaidCount = Payment::where('payment_status', 'unpaid')->count();

        // Angka akademik menghitung semua murid — data akademik tidak lagi
        // disaring status tagihan. Tunggakan punya scorecard-nya sendiri.
        $pendingReplacements = ReplacementRequest::where('request_status', 'pending')->count();
        $todayAttendance = Attendance::whereDate('attendance_date', today())->count();

        // Murid yang punya invoice lewat jatuh tempo, plus yang sudah ditangguhkan.
        $studentsInArrears = Student::inArrears()->count();
        $suspendedStudents = Student::whereNotNull('suspended_at')->count();

        // Metrik pelengkap untuk visual scorecard (Opsi 2)
        $studentActiveRate = $totalStudents > 0 ? round(($activeStudents / $totalStudents) * 100) : 0;
        $monthNet = $monthIncome - $monthExpense;
        $totalCapacity = (int) ClassRoom::where('status', 'open')->sum('capacity');
        $enrolledStudentsCount = (int) \Illuminate\Support\Facades\DB::table('student_class')->where('status', 'active')->count();
        $classOccupancyRate = $totalCapacity > 0 ? min(100, round(($enrolledStudentsCount / $totalCapacity) * 100)) : 0;
        $arrearsRate = $totalStudents > 0 ? round(($studentsInArrears / $totalStudents) * 100) : 0;

        // Grafik pertumbuhan murid 1 tahun terakhir (12 bulan secara realtime,
        // kumulatif murid aktif, konsisten dengan scorecard "Total Murid").
        $growthLabels = [];
        $growthFullLabels = [];
        $growthData = [];
        $baseDate = now()->startOfMonth();
        for ($i = 11; $i >= 0; $i--) {
            $month = $baseDate->copy()->subMonths($i);
            $growthLabels[] = $month->translatedFormat('M \'y');
            $growthFullLabels[] = $month->translatedFormat('F Y');
            $growthData[] = Student::where('status', 'active')
                ->whereDate('join_date', '<=', $month->copy()->endOfMonth())
                ->count();
        }

        // Jumlah murid per tipe kelas (realtime)
        $typeDefinitions = [
            'preschool' => [
                'label' => 'Preschool',
                'badge' => 'bg-primary text-white',
                'icon' => 'bi bi-easel2-fill',
                'bg_subtle' => '#E0F2FE',
                'text_color' => '#0284C7',
            ],
            'coloring' => [
                'label' => 'Coloring',
                'badge' => 'bg-primary text-white',
                'icon' => 'bi bi-easel2-fill',
                'bg_subtle' => '#E0F2FE',
                'text_color' => '#0284C7',
            ],
            'drawing' => [
                'label' => 'Drawing',
                'badge' => 'bg-primary text-white',
                'icon' => 'bi bi-easel2-fill',
                'bg_subtle' => '#E0F2FE',
                'text_color' => '#0284C7',
            ],
        ];

        $rawTypeCounts = Student::select('class_type')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = "active" then 1 else 0 end) as active_count')
            ->selectRaw('sum(case when status = "inactive" then 1 else 0 end) as inactive_count')
            ->groupBy('class_type')
            ->get()
            ->keyBy(fn ($item) => strtolower($item->class_type ?? 'other'));

        $tutorCounts = ClassRoom::whereNotNull('tutor_id')
            ->select('class_category')
            ->selectRaw('count(distinct tutor_id) as total_tutors')
            ->groupBy('class_category')
            ->pluck('total_tutors', 'class_category')
            ->mapWithKeys(fn ($count, $key) => [strtolower($key) => (int) $count]);

        $studentsPerClassType = [];
        foreach ($typeDefinitions as $key => $meta) {
            $stat = $rawTypeCounts->get($key);
            $total = $stat ? (int) $stat->total : 0;
            $active = $stat ? (int) $stat->active_count : 0;
            $inactive = $stat ? (int) $stat->inactive_count : 0;
            $tutorCount = $tutorCounts->get($key, 0);

            $studentsPerClassType[$key] = [
                'key' => $key,
                'label' => $meta['label'],
                'badge' => $meta['badge'],
                'icon' => $meta['icon'],
                'bg_subtle' => $meta['bg_subtle'],
                'text_color' => $meta['text_color'],
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'tutor_count' => $tutorCount,
                'percentage' => $totalStudents > 0 ? round(($total / $totalStudents) * 100) : 0,
            ];
        }

        foreach ($rawTypeCounts as $key => $stat) {
            if (!isset($studentsPerClassType[$key])) {
                $total = (int) $stat->total;
                $active = (int) $stat->active_count;
                $inactive = (int) $stat->inactive_count;
                $tutorCount = $tutorCounts->get($key, 0);

                $studentsPerClassType[$key] = [
                    'key' => $key,
                    'label' => ucfirst($key),
                    'badge' => 'bg-primary text-white',
                    'icon' => 'bi bi-easel2-fill',
                    'bg_subtle' => '#E0F2FE',
                    'text_color' => '#0284C7',
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $inactive,
                    'tutor_count' => $tutorCount,
                    'percentage' => $totalStudents > 0 ? round(($total / $totalStudents) * 100) : 0,
                ];
            }
        }

        $recentPayments = Payment::with('student')->orderByDesc('id')->limit(5)->get();

        return view('dashboard.index', compact(
            'totalStudents',
            'activeStudents',
            'inactiveStudents',
            'totalClasses',
            'totalIncome',
            'monthIncome',
            'monthExpense',
            'unpaidCount',
            'studentsInArrears',
            'suspendedStudents',
            'pendingReplacements',
            'todayAttendance',
            'studentActiveRate',
            'monthNet',
            'totalCapacity',
            'enrolledStudentsCount',
            'classOccupancyRate',
            'arrearsRate',
            'growthLabels',
            'growthFullLabels',
            'growthData',
            'studentsPerClassType',
            'recentPayments'
        ));
    }
}
