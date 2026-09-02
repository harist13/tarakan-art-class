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
        $activeClasses = ClassRoom::where('status', 'open')->count();

        // Angka pendapatan hanya untuk Super Admin. Untuk admin biasa nominalnya
        // tidak dihitung dan tidak dikirim ke view sama sekali, bukan sekadar
        // disembunyikan di tampilan.
        $canViewFinance = auth()->user()?->isSuperAdmin() ?? false;

        $totalIncome = $canViewFinance
            ? Transaction::where('type', 'income')->sum('amount')
            : 0;

        $monthIncome = $canViewFinance
            ? Transaction::where('type', 'income')
                ->whereYear('transaction_date', now()->year)
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount')
            : 0;

        $monthExpense = $canViewFinance
            ? Transaction::where('type', 'expense')
                ->whereYear('transaction_date', now()->year)
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount')
            : 0;

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

        // Jumlah murid per tipe kelas (realtime).
        //
        // Kategorinya tidak lagi tetap: sejak `classes.class_category` menjadi teks
        // bebas, daftar tipe di sini diturunkan dari data — gabungan tipe kelas yang
        // dipakai murid dan kategori yang ada di Class Management. Kategori yang baru
        // dibuat admin langsung muncul (walau belum ada muridnya), dan kategori yang
        // tak lagi dipakai hilang sendiri, bukan tersisa sebagai baris nol permanen.
        //
        // Pengelompokan memakai kunci huruf kecil supaya "Drawing" dan "drawing"
        // terhitung satu, tapi labelnya memakai ejaan asli seperti yang diketik admin.
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
            ->mapWithKeys(fn ($count, $key) => [strtolower((string) $key) => (int) $count]);

        // Ejaan asli per kunci. Kategori di tabel `classes` didahulukan karena itulah
        // yang diketik admin; `students.class_type` hanya jadi cadangan untuk tipe
        // lama yang kelasnya sudah dihapus.
        $typeLabels = Student::query()->distinct()->pluck('class_type')
            ->concat(ClassRoom::query()->distinct()->pluck('class_category'))
            ->filter(fn ($label) => filled($label))
            ->mapWithKeys(fn ($label) => [strtolower($label) => $label])
            ->all();

        $studentsPerClassType = [];
        foreach ($typeLabels as $key => $label) {
            $stat = $rawTypeCounts->get($key);
            $total = $stat ? (int) $stat->total : 0;

            $studentsPerClassType[$key] = [
                'key' => $key,
                'label' => $label,
                'badge' => 'bg-primary text-white',
                'icon' => 'bi bi-easel2-fill',
                'bg_subtle' => '#E0F2FE',
                'text_color' => '#0284C7',
                'total' => $total,
                'active' => $stat ? (int) $stat->active_count : 0,
                'inactive' => $stat ? (int) $stat->inactive_count : 0,
                'tutor_count' => $tutorCounts->get($key, 0),
                'percentage' => $totalStudents > 0 ? round(($total / $totalStudents) * 100) : 0,
            ];
        }

        // Tipe yang paling banyak muridnya di atas; nama sebagai pemecah seri agar
        // urutannya tidak berubah-ubah antar-permintaan.
        uasort($studentsPerClassType, fn (array $a, array $b) => [$b['total'], $a['label']] <=> [$a['total'], $b['label']]);

        $recentPayments = Payment::with('student')->orderByDesc('id')->limit(5)->get();

        return view('dashboard.index', compact(
            'canViewFinance',
            'totalStudents',
            'activeStudents',
            'inactiveStudents',
            'totalClasses',
            'activeClasses',
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
