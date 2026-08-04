<nav id="sidebar">
    <div class="sidebar-brand d-flex align-items-center justify-content-center">
        <div class="sidebar-brand-icon me-2">
            <i class="bi bi-palette-fill"></i>
        </div>
        <div class="sidebar-brand-text ms-1">Tarakan Art Class</div>
    </div>

    <ul class="list-unstyled components">
        <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        </li>

        <li class="px-4 mt-4 mb-2 text-uppercase sidebar-heading">Manajemen</li>
        @if(Auth::user()->isSuperAdmin())
        <li class="{{ request()->is('users*') ? 'active' : '' }}">
            <a href="{{ route('users.index') }}"><i class="bi bi-shield-lock-fill"></i> Users & Admin</a>
        </li>
        <li class="{{ request()->is('activity-logs*') ? 'active' : '' }}">
            <a href="{{ route('activity-logs.index') }}"><i class="bi bi-clock-history"></i> Log Aktivitas</a>
        </li>
        @endif
        <li class="{{ request()->is('students*') ? 'active' : '' }}">
            <a href="{{ route('students.index') }}"><i class="bi bi-people-fill"></i> Murid & Wali</a>
        </li>

        <li class="px-4 mt-4 mb-2 text-uppercase sidebar-heading">Akademik</li>
        <li class="{{ request()->is('classes*') ? 'active' : '' }}">
            <a href="{{ route('classes.index') }}"><i class="bi bi-easel2-fill"></i> Manajemen Kelas</a>
        </li>
        <li class="{{ request()->is('schedules/calendar') ? 'active' : '' }}">
            <a href="{{ route('schedules.calendar') }}"><i class="bi bi-calendar3-week-fill"></i> Kalender Jadwal</a>
        </li>
        <li class="{{ (request()->is('schedules*') && ! request()->is('schedules/calendar')) ? 'active' : '' }}">
            <a href="{{ route('schedules.index') }}"><i class="bi bi-calendar-range-fill"></i> Jadwal & Replacement</a>
        </li>
        <li class="{{ request()->is('attendances*') ? 'active' : '' }}">
            <a href="{{ route('attendances.index') }}"><i class="bi bi-person-check-fill"></i> Absensi Kelas</a>
        </li>
        <li class="{{ request()->is('reports*') ? 'active' : '' }}">
            <a href="{{ route('reports.index') }}"><i class="bi bi-journal-bookmark-fill"></i> Raport Siswa</a>
        </li>

        <li class="px-4 mt-4 mb-2 text-uppercase sidebar-heading">Keuangan & Ops</li>
        <li class="{{ request()->is('payments*') ? 'active' : '' }}">
            <a href="{{ route('payments.index') }}"><i class="bi bi-receipt-cutoff"></i> Pembayaran</a>
        </li>
        <li class="{{ request()->is('financials*') ? 'active' : '' }}">
            <a href="{{ route('financials.index') }}"><i class="bi bi-cash-stack"></i> Laporan Keuangan</a>
        </li>
        <li class="{{ request()->is('inventory*') ? 'active' : '' }}">
            <a href="{{ route('inventory.index') }}"><i class="bi bi-box-seam-fill"></i> Inventaris Barang</a>
        </li>
    </ul>
</nav>
