{{-- Apply saved theme before paint to avoid flash --}}
<script>
    (function () {
        var t = localStorage.getItem('tac-theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
</script>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<!-- Tom Select (searchable dropdowns) -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    :root {
        --primary-color: #0EA5E9;   /* Sky 500 */
        --primary-dark: #0369A1;    /* Sky 700 */
        --primary-light: #38BDF8;   /* Sky 400 */
        --secondary-color: #06B6D4; /* Cyan  500 */
        --success-color: #10B981;
        --info-color: #0EA5E9;
        --warning-color: #F59E0B;
        --danger-color: #EF4444;

        /* High Contrast UI Badge Tokens (WCAG Compliant) */
        --badge-success-bg: #15803D;
        --badge-warning-bg: rgba(245, 136, 12, 1);
        --badge-danger-bg:  #DC2626;
        --badge-info-bg:    #0891B2;
        --badge-primary-bg: #4F46E5;
        --badge-neutral-bg: #475569;
        --badge-text-light: #FFFFFF;

        /* Light theme surfaces */
        --bg: #EFF6FF;
        --bg-accent: #DBEAFE;
        --surface: #FFFFFF;
        --surface-2: #F0F9FF;
        --text: #1E293B;
        --text-muted: #64748B;
        --border: #E1EAF4;
        --topbar-bg: rgba(255, 255, 255, 0.9);
        --sidebar-from: #0EA5E9;
        --sidebar-to: #2563EB;
        --shadow-sm: rgba(2, 132, 199, 0.06);
        --shadow-md: rgba(2, 132, 199, 0.10);
    }

    [data-bs-theme="dark"] {
        --bg: #0B1220;
        --bg-accent: #0F1B30;
        --surface: #111E33;
        --surface-2: #16243C;
        --text: #E2E8F0;
        --text-muted: #94A3B8;
        --border: #22334E;
        --topbar-bg: rgba(17, 30, 51, 0.9);
        --sidebar-from: #0C4A6E;
        --sidebar-to: #1E3A8A;
        --shadow-sm: rgba(0, 0, 0, 0.30);
        --shadow-md: rgba(0, 0, 0, 0.45);
    }

    body {
        font-family: 'Nunito', sans-serif;
        background-color: var(--bg);
        background-image:
            radial-gradient(1200px 600px at 100% -10%, var(--bg-accent) 0%, transparent 55%),
            radial-gradient(900px 500px at -10% 110%, var(--bg-accent) 0%, transparent 50%);
        background-attachment: fixed;
        overflow-x: hidden;
        color: var(--text);
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* Sidebar Styles */
    #sidebar {
        min-width: 260px;
        max-width: 260px;
        height: 100vh;
        position: sticky;
        top: 0;
        display: flex;
        flex-direction: column;
        background: linear-gradient(160deg, var(--sidebar-from) 0%, var(--sidebar-to) 100%);
        color: white;
        transition: margin 0.3s ease, background 0.3s ease;
        box-shadow: 4px 0 24px var(--shadow-md);
        z-index: 1000;
    }

    #sidebar.collapsed {
        margin-left: -260px;
    }

    #sidebar .sidebar-brand {
        flex-shrink: 0;
        padding: 1.5rem 1rem;
        text-align: center;
        font-weight: 800;
        letter-spacing: 0.5px;
        background: rgba(255, 255, 255, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    }

    #sidebar .sidebar-brand-text {
        font-size: 1rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    #sidebar .sidebar-brand-icon {
        display: inline-block;
        background: #fff;
        color: var(--primary-color);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        line-height: 40px;
        text-align: center;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    #sidebar ul.components {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 1.5rem 0;
        /* Firefox scrollbar */
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
    }

    /* WebKit scrollbar */
    #sidebar ul.components::-webkit-scrollbar {
        width: 6px;
    }
    #sidebar ul.components::-webkit-scrollbar-track {
        background: transparent;
    }
    #sidebar ul.components::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.25);
        border-radius: 10px;
    }
    #sidebar ul.components::-webkit-scrollbar-thumb:hover {
        background-color: rgba(255, 255, 255, 0.45);
    }

    #sidebar ul li a {
        padding: 0.8rem 1.8rem;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        color: rgba(255, 255, 255, 0.82);
        text-decoration: none;
        transition: 0.25s ease;
        position: relative;
    }

    #sidebar ul li a:hover, #sidebar ul li.active > a {
        color: #fff;
        background: rgba(255, 255, 255, 0.16);
        font-weight: 700;
    }

    #sidebar ul li.active > a::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background-color: #fff;
        border-radius: 0 4px 4px 0;
    }

    #sidebar ul li a i {
        margin-right: 12px;
        font-size: 1.2rem;
        width: 20px;
        text-align: center;
    }

    .sidebar-heading {
        font-size: 0.7rem;
        font-weight: 800;
        color: rgba(255, 255, 255, 0.5);
        letter-spacing: 1px;
    }

    /* Content Styles */
    #content {
        width: 100%;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* Topbar */
    .topbar {
        position: relative;
        z-index: 1030;
        background-color: var(--topbar-bg);
        backdrop-filter: blur(10px);
        box-shadow: 0 2px 16px var(--shadow-sm);
        padding: 0.75rem 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    /* Ensure user/nav dropdowns float above page content */
    .topbar .dropdown-menu {
        z-index: 1040;
    }

    .topbar-icon-btn {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: var(--text-muted);
        border: 1px solid var(--border);
        background: var(--surface);
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .topbar-icon-btn:hover {
        color: var(--primary-color);
        border-color: var(--primary-light);
        transform: translateY(-1px);
    }

    /* Form Control / Inputs */
    .form-control, .form-select {
        border-radius: 0.5rem;
        border-color: var(--border);
        padding: 0.5rem 1rem;
        background-color: var(--surface);
        color: var(--text);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(14, 165, 233, 0.15);
        background-color: var(--surface);
        color: var(--text);
    }

    /* Card Styles */
    .card {
        border: 1px solid var(--border);
        border-radius: 1rem;
        background-color: var(--surface);
        box-shadow: 0 6px 24px var(--shadow-sm);
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px var(--shadow-md);
    }

    /* Kartu yang di-hover harus menang lapisan atas kartu sesudahnya, supaya
       dropdown di dalamnya tidak tertimpa. (transform membuat stacking context) */
    .card:hover, .card:focus-within {
        z-index: 5;
    }

    /* Saat ada dropdown terbuka di dalam kartu, matikan efek angkat: transform
       mengurung dropdown di dalam stacking context kartu sehingga bertabrakan
       dengan kartu berikutnya. */
    .card:has(.ts-wrapper.focus),
    .card:has(.dropdown-menu.show) {
        transform: none !important;
        z-index: 20;
    }

    .card-header {
        background-color: var(--surface);
        border-bottom: 1px solid var(--border);
        font-weight: 700;
        color: var(--text);
        padding: 1.25rem 1.5rem;
        border-radius: 1rem 1rem 0 0 !important;
    }

    .card-body {
        padding: 1.5rem;
    }

    .border-left-primary { border-left: 0.35rem solid var(--primary-color) !important; }
    .border-left-success { border-left: 0.35rem solid var(--success-color) !important; }
    .border-left-info    { border-left: 0.35rem solid var(--info-color) !important; }
    .border-left-warning { border-left: 0.35rem solid var(--warning-color) !important; }

    /* Tables */
    .table {
        --bs-table-bg: transparent;
        color: var(--text);
    }
    .table thead th {
        background-color: var(--surface-2);
        color: var(--text-muted);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border);
        padding: 1rem;
    }
    .table tbody td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        color: var(--text);
    }
    .table-hover tbody tr:hover > * {
        background-color: var(--surface-2);
    }

    /* Badges */
    .badge {
        padding: 0.4em 0.8em;
        border-radius: 30px;
        font-weight: 600;
        letter-spacing: 0.3px;
    }

    /* Buttons */
    .btn {
        border-radius: 0.5rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        transition: all 0.2s;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
        color: #fff;
        transform: translateY(-1px);
    }

    .btn-outline-primary {
        color: var(--primary-color);
        border-color: var(--primary-color);
    }
    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
    }

    .btn-info {
        background-color: #0891B2 !important;
        border-color: #0891B2 !important;
        color: #fff !important;
    }
    .btn-info:hover, .btn-info:focus, .btn-info:active {
        background-color: #0E7490 !important;
        border-color: #0E7490 !important;
        color: #fff !important;
        transform: translateY(-1px);
    }

    .btn-outline-info {
        color: #0891B2 !important;
        border-color: #0891B2 !important;
    }
    .btn-outline-info:hover, .btn-outline-info:focus, .btn-outline-info:active {
        background-color: #0891B2 !important;
        border-color: #0891B2 !important;
        color: #fff !important;
    }

    .btn-sm {
        padding: 0.3rem 0.7rem;
    }

    /* Hijau merek WhatsApp — sengaja beda dari btn-success (Konfirmasi Lunas)
       agar dua tombol hijau di kolom Aksi tidak tertukar. */
    .btn-whatsapp {
        background-color: #25D366;
        border-color: #25D366;
        color: #fff;
    }
    .btn-whatsapp:hover, .btn-whatsapp:focus {
        background-color: #1DA851;
        border-color: #1DA851;
        color: #fff;
        transform: translateY(-1px);
    }

    /* Text helpers used across views */
    .text-primary { color: var(--primary-color) !important; }
    .text-gray-800, .text-gray-700 { color: var(--text) !important; }
    .text-gray-400, .text-muted { color: var(--text-muted) !important; }

    .dropdown-menu {
        background-color: var(--surface);
        border: 1px solid var(--border);
    }
    .dropdown-item { color: var(--text); }
    .dropdown-item:hover { background-color: var(--surface-2); color: var(--primary-color); }

    /* Footer */
    .sticky-footer {
        background-color: var(--surface);
        border-top: 1px solid var(--border);
    }

    .sticky-footer a:hover {
        color: var(--primary-color) !important;
    }

    /* Flex grow for main container */
    .main-container {
        flex: 1 0 auto;
    }

    /* ── Tom Select (searchable dropdown) — selaraskan dengan tema ── */
    .ts-control {
        background-color: var(--surface) !important;
        border-color: var(--border) !important;
        color: var(--text) !important;
        border-radius: 0.5rem !important;
    }
    .ts-control, .ts-control input, .ts-control .item { color: var(--text) !important; }
    .ts-control input::placeholder { color: var(--text-muted) !important; }
    .ts-wrapper { position: relative; }
    /* Kontrol yang sedang aktif ikut diangkat agar dropdown-nya di atas elemen lain. */
    .ts-wrapper.focus { z-index: 1055; }
    .ts-dropdown {
        background-color: var(--surface);
        border: 1px solid var(--border);
        color: var(--text);
        box-shadow: 0 12px 32px var(--shadow-md);
        border-radius: 0.65rem;
        margin-top: 0.35rem;
        padding: 0;
        overflow: hidden;
        z-index: 1056;
    }
    .ts-dropdown .ts-dropdown-content { max-height: 240px; padding: 0.25rem; }
    .ts-dropdown .option {
        color: var(--text);
        padding: 0.5rem 0.75rem;
        font-size: 0.95rem;
        line-height: 1.3;
        border-radius: 0.4rem;
    }
    .ts-dropdown .option.active { background-color: var(--surface-2); color: var(--primary-color); }
    .ts-dropdown .option.selected { font-weight: 700; }
    /* Dropdown di dalam tabel tidak boleh terpotong overflow-x milik .table-responsive. */
    .table-responsive:has(.ts-wrapper.focus) { overflow: visible; }
    /* Kotak pencarian di dalam dropdown (plugin dropdown_input) */
    .ts-dropdown .dropdown-input-wrap { padding: 0.4rem; border-bottom: 1px solid var(--border); }
    .ts-dropdown .dropdown-input {
        background-color: var(--surface);
        border: 1px solid var(--border);
        color: var(--text);
        border-radius: 0.4rem;
        padding: 0.35rem 0.6rem;
        width: 100%;
    }
    .ts-dropdown .dropdown-input::placeholder { color: var(--text-muted); }
    .ts-dropdown .dropdown-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 0.15rem rgba(14,165,233,0.15); }
    .ts-dropdown .no-results { color: var(--text-muted); }
    .ts-dropdown .optgroup-header { color: var(--text-muted); background: var(--surface-2); font-weight: 700; }
    .ts-control:focus-within { border-color: var(--primary-color) !important; box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.15); }
    /* Tinggi kontrol Tom Select -sm disamakan PERSIS dengan field search/date ber-py-2.
       Target = font(0.875rem)*line-height(1.5) + padding py-2(2*0.5rem) + border(2px). */
    .tac-ts-sm.ts-wrapper .ts-control {
        min-height: calc(0.875rem * 1.5 + 1rem + 2px) !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        padding-left: 0.75rem !important;
        display: flex !important;
        align-items: center !important;
        font-size: 0.875rem;
    }
    .tac-ts-sm.ts-wrapper .ts-control > .item,
    .tac-ts-sm.ts-wrapper .ts-control > input {
        line-height: 1.5;
        margin: 0 !important;
    }
</style>
