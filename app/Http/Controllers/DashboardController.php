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

        // Grafik pertumbuhan murid 6 bulan terakhir (kumulatif murid aktif,
        // konsisten dengan scorecard "Total Murid" yang membedakan aktif/nonaktif).
        $growthLabels = [];
        $growthData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $growthLabels[] = $month->translatedFormat('M Y');
            $growthData[] = Student::where('status', 'active')
                ->whereDate('join_date', '<=', $month->copy()->endOfMonth())
                ->count();
        }

        // Kelas terlaris (berdasarkan jumlah murid).
        $topClasses = ClassRoom::withCount('students')
            ->orderByDesc('students_count')
            ->limit(5)
            ->get();

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
            'growthLabels',
            'growthData',
            'topClasses',
            'recentPayments'
        ));
    }
}
