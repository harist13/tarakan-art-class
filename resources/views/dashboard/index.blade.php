@extends('layouts.app')

@section('content')
<!-- Welcome Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-gray-800 mb-1">
            Selamat Datang, {{ auth()->user()->full_name ?? auth()->user()->username ?? 'Admin' }}! 👋
        </h2>
        <p class="text-muted small mb-0">Pantau performa kelas, pertumbuhan murid, dan transaksi keuangan secara realtime.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="d-inline-flex align-items-center bg-white border px-3 py-2 rounded-pill shadow-sm">
            <i class="bi bi-calendar3 text-primary me-2"></i>
            <span class="small fw-semibold text-gray-700">{{ now()->translatedFormat('l, d F Y') }}</span>
        </div>
    </div>
</div>

<!-- Scorecards -->
@php
    // Tanpa kartu Total Pendapatan (admin biasa), tiga kartu sisanya dilebarkan
    // supaya barisnya tetap penuh dan tidak menyisakan kolom kosong.
    $scoreCol = $canViewFinance ? 'col-xl-3' : 'col-xl-4';
@endphp
<div class="row mb-4">
    <!-- Total Murid -->
    <div class="{{ $scoreCol }} col-md-6 mb-3 mb-xl-0">
        <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden"
             style="background: var(--surface); border: 1px solid var(--border) !important;">
            <div class="position-absolute top-0 start-0 end-0" style="height: 3.5px; background: #0EA5E9;"></div>
            <div class="card-body p-3 px-3 py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold" style="font-size: 0.84rem;">Total Murid</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 34px; height: 34px; background: #E0F2FE; color: #0284C7;">
                        <i class="bi bi-people fs-6"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-3 text-gray-900" style="font-size: 1.75rem; line-height: 1.1;">{{ number_format($totalStudents) }}</h3>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge rounded-pill px-2 py-1 fw-semibold text-nowrap" style="font-size: 0.76rem; background: #E0F2FE; color: #0284C7;">
                        {{ $activeStudents }} Aktif
                    </span>
                    <span class="badge rounded-pill px-2 py-1 text-nowrap" style="font-size: 0.76rem; background: #F1F5F9; color: #374151; border: 1px solid #E2E8F0;">
                        {{ $inactiveStudents }} Nonaktif
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Pendapatan — hanya Super Admin --}}
    @if($canViewFinance)
    <div class="{{ $scoreCol }} col-md-6 mb-3 mb-xl-0">
        <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden"
             style="background: var(--surface); border: 1px solid var(--border) !important;">
            <div class="position-absolute top-0 start-0 end-0" style="height: 3.5px; background: #10B981;"></div>
            <div class="card-body p-3 px-3 py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold" style="font-size: 0.84rem;">Total Pendapatan</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 34px; height: 34px; background: #DCFCE7; color: #16A34A;">
                        <i class="bi bi-cash-stack fs-6"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-3 text-gray-900" style="font-size: 1.55rem; line-height: 1.1;">Rp {{ number_format($totalIncome, 0, ',', '.') }}</h3>
                {{-- Pemasukan kiri, pengeluaran kanan — masing-masing separuh lebar dan
                     tidak boleh turun baris. Angkanya sengaja tidak dikunci text-nowrap:
                     nominal yang sangat panjang lebih baik melipat di dalam pill-nya
                     daripada mendorong pasangannya ke baris berikutnya. --}}
                <div class="d-flex align-items-stretch gap-2 flex-nowrap">
                    <span class="badge rounded-pill px-2 py-1 fw-semibold flex-fill text-center d-flex align-items-center justify-content-center"
                          style="font-size: 0.74rem; background: #DCFCE7; color: #16A34A;" title="Pendapatan bulan ini">
                        &uarr; Rp {{ number_format($monthIncome, 0, ',', '.') }}
                    </span>
                    <span class="badge rounded-pill px-2 py-1 flex-fill text-center d-flex align-items-center justify-content-center"
                          style="font-size: 0.74rem; background: #F1F5F9; color: #374151; border: 1px solid #E2E8F0;" title="Pengeluaran bulan ini">
                        &darr; Rp {{ number_format($monthExpense, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Total Kelas -->
    <div class="{{ $scoreCol }} col-md-6 mb-3 mb-xl-0">
        <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden"
             style="background: var(--surface); border: 1px solid var(--border) !important;">
            <div class="position-absolute top-0 start-0 end-0" style="height: 3.5px; background: #6366F1;"></div>
            <div class="card-body p-3 px-3 py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold" style="font-size: 0.84rem;">Total Kelas</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 34px; height: 34px; background: #EDE9FE; color: #6366F1;">
                        <i class="bi bi-display fs-6"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-3 text-gray-900" style="font-size: 1.75rem; line-height: 1.1;">{{ number_format($totalClasses) }}</h3>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge rounded-pill px-2 py-1 fw-semibold text-nowrap" style="font-size: 0.76rem; background: #EDE9FE; color: #6366F1;">
                        {{ $activeClasses }} Aktif
                    </span>
                    <span class="badge rounded-pill px-2 py-1 text-nowrap" style="font-size: 0.76rem; background: #F1F5F9; color: #374151; border: 1px solid #E2E8F0;">
                        {{ $todayAttendance }} Hadir Hari Ini
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Perlu Perhatian -->
    <div class="{{ $scoreCol }} col-md-6 mb-3 mb-xl-0">
        <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden"
             style="background: var(--surface); border: 1px solid var(--border) !important;">
            <div class="position-absolute top-0 start-0 end-0" style="height: 3.5px; background: rgba(245, 136, 12, 1);"></div>
            <div class="card-body p-3 px-3 py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold" style="font-size: 0.84rem;">Perlu Perhatian</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 34px; height: 34px; background: #FEF3C7; color: rgba(245, 136, 12, 1);">
                        <i class="bi bi-exclamation-triangle fs-6"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-3 text-gray-900" style="font-size: 1.75rem; line-height: 1.1;">{{ $studentsInArrears }}</h3>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge rounded-pill px-2 py-1 fw-semibold text-nowrap" style="font-size: 0.76rem; background: #FEF3C7; color: rgba(245, 136, 12, 1);">
                        {{ $unpaidCount }} Tagihan Pending
                    </span>
                    <span class="badge rounded-pill px-2 py-1 text-nowrap" style="font-size: 0.76rem; background: #F1F5F9; color: #374151; border: 1px solid #E2E8F0;">
                        {{ $pendingReplacements }} Replacement
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Middle Section: Growth Chart & Class Types -->
<div class="row mb-4">
    <!-- Growth Chart (1 Year) -->
    <div class="col-xl-8 col-lg-7 mb-4 mb-lg-0">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: var(--surface); border: 1px solid var(--border) !important;">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3 px-4">
                <div>
                    <h5 class="fw-bold mb-0 text-gray-800" style="font-size: 1.05rem;">Pertumbuhan Murid (1 Tahun Terakhir)</h5>
                    <div class="text-muted small" style="font-size: 0.78rem;">Kumulatif murid aktif per bulan</div>
                </div>
            </div>
            <div class="card-body p-4">
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Students per Class Type -->
    <div class="col-xl-4 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: var(--surface); border: 1px solid var(--border) !important;">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3 px-4">
                <div>
                    <h5 class="fw-bold mb-0 text-gray-800" style="font-size: 1.05rem;">Murid per Kategori Kelas</h5>
                    <div class="text-muted small" style="font-size: 0.78rem;">Distribusi murid dan jumlah tutor</div>
                </div>
                <span class="badge bg-light text-dark border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                    {{ $totalStudents }} Total
                </span>
            </div>
            {{-- Hanya 3 kategori yang terlihat; sisanya dicapai lewat scroll di dalam
                 daftar, ditandai panah di tepi bawah selama masih ada yang tersembunyi. --}}
            <div class="card-body p-3 p-md-4 position-relative">
                <div id="classCategoryList" class="class-category-list">
                    @forelse($studentsPerClassType as $type)
                        <div class="d-flex align-items-center justify-content-between p-3 mb-2 rounded-3"
                             style="background: {{ $type['bg_subtle'] }}; border: 1px solid rgba(0,0,0,0.04);">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width: 38px; height: 38px; background: white; color: {{ $type['text_color'] }}; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                    <i class="{{ $type['icon'] }} fs-6"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-gray-800 mb-0" style="font-size: 0.95rem;">{{ $type['label'] }}</div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-person-badge me-1"></i>{{ $type['tutor_count'] }} tutor
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-2 fw-bold" style="font-size: 0.8rem; box-shadow: 0 2px 6px rgba(14, 165, 233, 0.25);">
                                {{ $type['total'] }} murid
                            </span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0">Belum ada data murid.</p>
                    @endforelse
                </div>
                @if(count($studentsPerClassType) > 3)
                    <button type="button" id="classCategoryScrollHint" class="class-category-hint" aria-label="Lihat kategori lainnya">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card border-0 shadow-sm rounded-4 mb-4" style="background: var(--surface); border: 1px solid var(--border) !important;">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3 px-4">
        <div>
            <h5 class="fw-bold mb-0 text-gray-800" style="font-size: 1.05rem;">Pembayaran Terbaru</h5>
            <div class="text-muted small" style="font-size: 0.78rem;">Riwayat 5 transaksi dan invoice terbaru</div>
        </div>
        <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center" style="font-size: 0.82rem;">
            Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.6px; background-color: var(--surface-2);">
                        <th class="ps-4 py-3">Murid</th>
                        <th class="py-3">No. Invoice</th>
                        <th class="py-3">Tanggal</th>
                        <th class="py-3">Jumlah</th>
                        <th class="py-3">Status</th>
                        <th class="text-end pe-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $payment)
                        @php
                            $initial = strtoupper(substr($payment->student->name ?? 'M', 0, 1));
                            $bgColors = ['#E0F2FE', '#FEF3C7', '#EDE9FE', '#DCFCE7', '#FEE2E2'];
                            $textColors = ['#0284C7', '#D97706', '#7C3AED', '#16A34A', '#DC2626'];
                            $colorIndex = $loop->index % count($bgColors);
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                         style="width: 36px; height: 36px; background: {{ $bgColors[$colorIndex] }}; color: {{ $textColors[$colorIndex] }}; font-size: 0.85rem;">
                                        {{ $initial }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-gray-800 mb-0" style="font-size: 0.9rem;">{{ $payment->student->name ?? 'Murid #' . $payment->student_id }}</div>
                                        <div class="small text-muted" style="font-size: 0.75rem;">{{ $payment->student->student_id ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="fw-semibold text-primary font-monospace" style="font-size: 0.85rem;">{{ $payment->invoice_number }}</span>
                            </td>
                            <td class="py-3">
                                <span class="text-secondary" style="font-size: 0.85rem;">
                                    <i class="bi bi-calendar2 me-1 text-muted"></i>{{ $payment->payment_date->format('d M Y') }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="fw-bold text-gray-800" style="font-size: 0.9rem;">Rp {{ number_format($payment->payment_amount, 0, ',', '.') }}</span>
                            </td>
                            <td class="py-3">
                                @if($payment->payment_status === 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-check-circle-fill me-1"></i>Lunas
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock-fill me-1"></i>Belum Lunas
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-4 py-3">
                                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-light border rounded-circle shadow-xs"
                                   style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;"
                                   title="Lihat Detail">
                                    <i class="bi bi-eye text-primary"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada data pembayaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* 250px ≈ tiga baris kategori penuh plus sedikit potongan baris keempat, supaya
       terlihat bahwa daftarnya masih berlanjut sebelum panahnya diperhatikan. */
    .class-category-list {
        max-height: 250px;
        overflow-y: auto;
        scrollbar-width: thin;
        scroll-behavior: smooth;
        padding-right: 4px;
    }
    .class-category-list::-webkit-scrollbar { width: 6px; }
    .class-category-list::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.5);
        border-radius: 999px;
    }
    .class-category-list::-webkit-scrollbar-track { background: transparent; }

    .class-category-hint {
        position: absolute;
        right: 50%;
        bottom: 6px;
        transform: translateX(50%);
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 999px;
        background: var(--surface, #fff);
        color: #0284C7;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
        font-size: 0.8rem;
        opacity: 1;
        transition: opacity 0.2s ease;
    }
    /* Sudah mentok bawah: panahnya dilepas dari alur klik, bukan sekadar transparan. */
    .class-category-hint.is-hidden {
        opacity: 0;
        pointer-events: none;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const list = document.getElementById('classCategoryList');
        const hint = document.getElementById('classCategoryScrollHint');
        if (!list || !hint) return;

        const syncHint = () => {
            const atBottom = list.scrollTop + list.clientHeight >= list.scrollHeight - 4;
            hint.classList.toggle('is-hidden', atBottom);
        };

        // Satu klik menggeser kira-kira tiga baris — sejauh yang sedang terlihat.
        hint.addEventListener('click', () => list.scrollBy({ top: list.clientHeight, behavior: 'smooth' }));
        list.addEventListener('scroll', syncHint);
        window.addEventListener('resize', syncHint);
        syncHint();
    })();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('growthChart');
    if (ctx && window.Chart) {
        const chartCtx = ctx.getContext('2d');
        const gradient = chartCtx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(14, 165, 233, 0.25)');
        gradient.addColorStop(1, 'rgba(14, 165, 233, 0.00)');

        const fullMonthNames = @json($growthFullLabels);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($growthLabels),
                datasets: [{
                    label: 'Total Murid Aktif',
                    data: @json($growthData),
                    borderColor: '#0EA5E9',
                    borderWidth: 2.5,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0,
                    pointBackgroundColor: '#0EA5E9',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        padding: 12,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(items) {
                                if (!items.length) return '';
                                const idx = items[0].dataIndex;
                                return fullMonthNames[idx] || items[0].label;
                            },
                            label: function(context) {
                                return ' ' + context.parsed.y + ' Murid Aktif';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                            font: { size: 11, weight: '600' },
                            color: '#64748B',
                            padding: 8,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.7)', borderDash: [4, 4] },
                        ticks: { precision: 0, font: { size: 11 }, color: '#64748B', padding: 8 }
                    }
                }
            }
        });
    }
</script>
@endpush
