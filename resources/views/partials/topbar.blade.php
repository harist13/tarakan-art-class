<nav class="navbar navbar-expand topbar mb-4 static-top">
    <button id="sidebarToggle" class="topbar-icon-btn me-3">
        <i class="bi bi-list fs-5"></i>
    </button>

    <!-- Navbar Search -->
    <form class="d-none d-sm-inline-block form-inline me-auto ms-md-1 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group shadow-sm" style="border-radius: 0.5rem; overflow: hidden;">
            <input type="text" class="form-control border-0 small" placeholder="Cari data murid, kelas..." aria-label="Search">
            <button class="btn btn-primary" type="button">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </form>

    <ul class="navbar-nav ms-auto align-items-center">
        <!-- Theme toggle -->
        <li class="nav-item me-2">
            <button id="themeToggle" type="button" class="topbar-icon-btn" title="Ganti Tema Terang / Gelap">
                <i class="bi bi-moon-stars-fill"></i>
            </button>
        </li>
        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="me-3 d-none d-lg-inline small fw-bold" style="color: var(--text);">Halo, {{ Auth::user()->full_name }}</span>
                <img class="img-profile rounded-circle shadow-sm" src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->full_name) }}&background=0EA5E9&color=fff" width="36" height="36">
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm animated--grow-in mt-2" aria-labelledby="userDropdown">
                <li>
                    <span class="dropdown-item-text py-2">
                        <strong>{{ Auth::user()->full_name }}</strong><br>
                        <small class="text-muted">{{ Auth::user()->isSuperAdmin() ? 'Super Admin' : 'Admin' }}</small>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2" href="#"><i class="bi bi-person fa-sm fa-fw me-2 text-gray-400"></i> Profil Saya</a></li>
                <li><a class="dropdown-item py-2" href="#"><i class="bi bi-gear fa-sm fa-fw me-2 text-gray-400"></i> Pengaturan Sistem</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger py-2"><i class="bi bi-box-arrow-right fa-sm fa-fw me-2"></i> Keluar</button>
                    </form>
                </li>
            </ul>
        </li>
    </ul>
</nav>
