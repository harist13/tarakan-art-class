@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Dashboard Analytics</h1>
    <span class="badge bg-light text-dark border">{{ now()->translatedFormat('l, d F Y') }}</span>
</div>

<!-- Scorecards -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-primary text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Total Murid</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ number_format($totalStudents) }}</div>
                        <div class="small text-muted mt-1">{{ $activeStudents }} aktif &middot; {{ $inactiveStudents }} nonaktif</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-people-fill fs-1 text-gray-300" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-success text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Total Pendapatan</div>
                        <div class="h5 mb-0 fw-bold text-gray-800">Rp {{ number_format($totalIncome, 0, ',', '.') }}</div>
                        <div class="small text-muted mt-1">Bulan ini: Rp {{ number_format($monthIncome, 0, ',', '.') }} &middot; Pengeluaran: Rp {{ number_format($monthExpense, 0, ',', '.') }}</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-cash-stack fs-1" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-info text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Total Kelas</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ number_format($totalClasses) }}</div>
                        <div class="small text-muted mt-1">Absensi hari ini: {{ $todayAttendance }}</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-easel2-fill fs-1" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-warning text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Perlu Perhatian</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $unpaidCount }} <span class="small text-muted fw-normal">tagihan belum lunas</span></div>
                        <div class="small text-muted mt-1">{{ $pendingReplacements }} replacement pending</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-exclamation-triangle-fill fs-1" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Growth Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card">
            <div class="card-header">Pertumbuhan Murid (6 Bulan Terakhir)</div>
            <div class="card-body">
                <canvas id="growthChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <!-- Top Classes -->
    <div class="col-xl-4 col-lg-5">
        <div class="card">
            <div class="card-header">Kelas Terlaris</div>
            <div class="card-body">
                @forelse($topClasses as $class)
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-bold">{{ $class->class_name }}</div>
                            <div class="small text-muted">{{ $class->class_code }}</div>
                        </div>
                        <span class="badge bg-primary">{{ $class->students_count }} murid</span>
                    </div>
                @empty
                    <p class="text-muted text-center mb-0">Belum ada data kelas.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card">
    <div class="card-header">Pembayaran Terbaru</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Invoice</th><th>Murid</th><th>Tanggal</th><th>Jumlah</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $payment)
                        <tr>
                            <td class="fw-bold">{{ $payment->invoice_number }}</td>
                            <td>{{ $payment->student->name ?? '-' }}</td>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td>Rp {{ number_format($payment->payment_amount, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge bg-{{ $payment->payment_status === 'paid' ? 'success' : 'warning' }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Belum ada pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('growthChart');
    // Guard: jika Chart.js gagal dimuat (mis. CDN tidak tersedia), lewati agar tidak error.
    if (ctx && window.Chart) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($growthLabels),
            datasets: [{
                label: 'Total Murid',
                data: @json($growthData),
                borderColor: '#0EA5E9',
                backgroundColor: 'rgba(14,165,233,0.12)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#0EA5E9',
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    }
</script>
@endpush
