<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Export data murid (F2). Menghormati filter search & status dari halaman index. */
    public function students(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $classId = $request->integer('class_id');

        $students = Student::query()
            ->with('classes')
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('parent_name', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->when($classId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('classes.id', $classId)))
            ->orderByDesc('id')
            ->get();

        $headers = ['Student ID', 'Nama', 'Tgl Lahir', 'Usia', 'Orang Tua', 'Telepon', 'Kelas', 'Tipe Kelas', 'Status', 'Tgl Gabung'];

        $rows = $students->map(fn ($s) => [
            $s->student_id,
            $s->name,
            optional($s->date_of_birth)->format('d/m/Y'),
            $s->age !== null ? $s->age.' th' : '-',
            $s->parent_name,
            $s->phone_number,
            $s->classes->pluck('class_name')->implode(', ') ?: '-',
            ucfirst($s->class_type),
            $s->status === 'active' ? 'Aktif' : 'Nonaktif',
            optional($s->join_date)->format('d/m/Y'),
        ]);

        return $this->respond($request, 'Data Murid', $headers, $rows, 'data-murid');
    }

    /** Export pembayaran (F6). Menghormati filter search & status. */
    public function payments(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $payments = Payment::query()
            ->with('student')
            ->when($search, fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")))
            ->when(in_array($status, ['paid', 'unpaid'], true), fn ($q) => $q->where('payment_status', $status))
            ->orderByDesc('id')
            ->get();

        $headers = ['Invoice', 'Murid', 'Tanggal', 'Jumlah (Rp)', 'Metode', 'Status', 'Catatan'];

        $rows = $payments->map(fn ($p) => [
            $p->invoice_number,
            $p->student->name ?? '-',
            optional($p->payment_date)->format('d/m/Y'),
            number_format($p->payment_amount, 0, ',', '.'),
            $p->methodLabel(),
            $p->payment_status === 'paid' ? 'Lunas' : 'Belum Lunas',
            $p->notes ?: '-',
        ]);

        return $this->respond($request, 'Laporan Pembayaran', $headers, $rows, 'laporan-pembayaran');
    }

    /** Export laporan keuangan (F7). Menghormati filter month & type. */
    public function financials(Request $request)
    {
        $type = $request->string('type')->toString();
        // Aturan periode disamakan dengan halaman Laporan Keuangan (termasuk "Semua Bulan").
        $month = FinancialController::resolveMonth($request);
        $search = $request->string('search')->toString();

        [$year, $mon] = $month !== '' ? explode('-', $month) : [null, null];

        // Sama seperti halamannya: ringkasan dihitung dari periode + pencarian saja,
        // filter tipe hanya menyaring baris yang diekspor.
        $scope = Transaction::query()
            ->when($year && $mon, fn ($q) => $q->whereYear('transaction_date', $year)->whereMonth('transaction_date', $mon))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }));

        $transactions = (clone $scope)
            ->when(in_array($type, ['income', 'expense'], true), fn ($q) => $q->where('type', $type))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $headers = ['Tanggal', 'Tipe', 'Kategori', 'Jumlah (Rp)', 'Deskripsi'];

        $rows = $transactions->map(fn ($t) => [
            optional($t->transaction_date)->format('d/m/Y'),
            $t->type === 'income' ? 'Pemasukan' : 'Pengeluaran',
            $t->category,
            number_format($t->amount, 0, ',', '.'),
            $t->description ?: '-',
        ]);

        $income = (float) (clone $scope)->where('type', 'income')->sum('amount');
        $expense = (float) (clone $scope)->where('type', 'expense')->sum('amount');
        $meta = [
            'Total Pemasukan' => 'Rp '.number_format($income, 0, ',', '.'),
            'Total Pengeluaran' => 'Rp '.number_format($expense, 0, ',', '.'),
            'Saldo' => 'Rp '.number_format($income - $expense, 0, ',', '.'),
        ];

        $period = FinancialController::periodLabel($month);

        return $this->respond($request, 'Laporan Keuangan '.$period, $headers, $rows, 'laporan-keuangan-'.($month ?: 'semua-periode'), $meta);
    }

    /** Export absensi (F5). Menghormati filter class_id & date. */
    public function attendances(Request $request)
    {
        $classId = $request->integer('class_id');
        $date = $request->string('date')->toString();
        $search = $request->string('search')->toString();

        $attendances = Attendance::query()
            ->with(['student', 'classRoom'])
            // Sama seperti halaman absensi: rekap kehadiran tidak disaring tagihan.
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($date, fn ($q) => $q->whereDate('attendance_date', $date))
            ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")))
            ->orderByDesc('attendance_date')
            ->get();

        $headers = ['Tanggal', 'Murid', 'Kelas', 'Status', 'Catatan'];

        $statusLabels = ['present' => 'Hadir', 'absent' => 'Tidak Hadir', 'sick' => 'Sakit', 'permit' => 'Izin'];

        $rows = $attendances->map(fn ($a) => [
            optional($a->attendance_date)->format('d/m/Y'),
            $a->student->name ?? '-',
            $a->classRoom->class_name ?? '-',
            $statusLabels[$a->status] ?? ucfirst((string) $a->status),
            $a->notes ?: '-',
        ]);

        return $this->respond($request, 'Rekap Absensi', $headers, $rows, 'rekap-absensi');
    }

    /**
     * Dispatch ke CSV atau PDF berdasarkan query param `format` (default csv).
     */
    private function respond(Request $request, string $title, array $headers, $rows, string $filename, array $meta = [])
    {
        $rows = collect($rows)->map(fn ($r) => array_values((array) $r))->all();
        $format = $request->string('format')->lower()->toString();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.pdf', [
                'title' => $title,
                'headers' => $headers,
                'rows' => $rows,
                'meta' => $meta,
                'generatedAt' => now(),
                'generatedBy' => auth()->user()->full_name ?? '-',
            ])->setPaper('a4', count($headers) > 6 ? 'landscape' : 'portrait');

            return $pdf->download($filename.'-'.now()->format('Ymd-His').'.pdf');
        }

        return $this->csv($title, $headers, $rows, $filename);
    }

    /**
     * Streamed CSV dengan BOM UTF-8 agar karakter Indonesia terbaca di Excel.
     */
    private function csv(string $title, array $headers, array $rows, string $filename): StreamedResponse
    {
        $name = $filename.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
