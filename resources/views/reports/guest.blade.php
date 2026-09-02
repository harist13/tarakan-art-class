<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Siswa - Tarakan Art Class</title>
    <script>
        (function () {
            var t = localStorage.getItem('tac-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --grad-from: #38BDF8;
            --grad-to: #2563EB;
            --card-bg: #FFFFFF;
            --card-shadow: rgba(37, 99, 235, 0.20);
        }
        [data-bs-theme="dark"] {
            --grad-from: #0C4A6E;
            --grad-to: #1E3A8A;
            --card-bg: #111E33;
            --card-shadow: rgba(0, 0, 0, 0.5);
        }
        body {
            font-family: 'Nunito', sans-serif;
            background: linear-gradient(145deg, var(--grad-from) 0%, var(--grad-to) 100%);
            min-height: 100vh;
            transition: background 0.4s ease;
        }
        .brand { color: #fff; font-weight: 800; letter-spacing: 1px; }
        .card {
            border: none;
            border-radius: 1rem;
            background-color: var(--card-bg);
            box-shadow: 0 10px 40px var(--card-shadow);
        }
        .text-primary { color: #0EA5E9 !important; }
        .btn-primary { background-color: #0EA5E9; border-color: #0EA5E9; }
        .btn-primary:hover { background-color: #0369A1; border-color: #0369A1; }
        .btn-outline-primary { color: #0EA5E9; border-color: #0EA5E9; }
        .btn-outline-primary:hover { background-color: #0EA5E9; border-color: #0EA5E9; color: #fff; }
        .theme-toggle {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 10;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: all 0.25s ease;
        }
        .theme-toggle:hover { background: rgba(255, 255, 255, 0.28); transform: rotate(15deg); }

        /* ── Report card ────────────────────────────── */
        .report-card { overflow: hidden; }
        .report-hero {
            background: linear-gradient(135deg, #0EA5E9 0%, #2563EB 100%);
            padding: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
            color: #fff;
        }
        .hero-avatar {
            width: 84px; height: 84px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.6);
            font-size: 2rem; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .hero-main { flex: 1 1 180px; min-width: 0; }
        .hero-main h4 { font-weight: 800; margin: 0 0 .15rem; }
        .hero-sub { color: rgba(255,255,255,0.85); font-size: .85rem; }
        .hero-meta { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
        .class-chip {
            display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .8rem; border-radius: 30px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.35);
            font-size: .78rem; font-weight: 700; letter-spacing: .3px;
        }
        .report-section { padding: 1.25rem 1.5rem; border-top: 1px solid rgba(148,163,184,0.18); }
        .report-section h6 {
            font-weight: 800; text-transform: uppercase; letter-spacing: .5px; font-size: .75rem;
            color: #0EA5E9; display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem;
        }
        .report-section p { margin: 0; white-space: pre-line; line-height: 1.7; }

        /* ── Galeri karya ───────────────────────────── */
        .artwork-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: .75rem;
        }
        .artwork-item { margin: 0; }
        .artwork-item img {
            width: 100%; height: 140px; object-fit: cover;
            border-radius: .6rem; border: 1px solid rgba(148,163,184,0.35);
            background: rgba(148,163,184,0.12);
        }
        .artwork-item figcaption { padding-top: .35rem; line-height: 1.45; }
        .artwork-date { display: block; font-size: .72rem; font-weight: 700; color: #0EA5E9; }
        .artwork-desc { display: block; font-size: .75rem; opacity: .75; }

        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .theme-toggle, .brand, .text-white-50, .no-print { display: none !important; }
            .container { max-width: 100% !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; border-radius: 0 !important; }
            .report-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #0284C7 !important; color: #fff !important; }
            .class-chip { border: 1px solid #fff !important; color: #fff !important; }
            /* Grid dipersempit agar tiap karya tetap utuh di atas kertas. */
            .artwork-grid { grid-template-columns: repeat(3, 1fr) !important; }
            .artwork-item { break-inside: avoid; }
        }
    </style>
</head>
<body class="py-5">
    <button type="button" class="theme-toggle" id="themeToggle" title="Ganti Tema Terang / Gelap">
        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
    </button>
    <div class="container" style="max-width: 720px;">
        <div class="text-center mb-4">
            <div class="brand fs-3"><i class="bi bi-palette-fill me-2"></i>Tarakan Art Class</div>
            <p class="text-white-50 mb-0">Akses Raport Perkembangan Siswa</p>
        </div>

        @if(!isset($report))
            <div class="card">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:64px;height:64px;border-radius:50%;background:rgba(14,165,233,0.12);color:#0EA5E9;font-size:1.8rem;">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Masukkan Credential Key</h5>
                    <p class="text-muted small mb-4">Masukkan credential key yang diberikan admin untuk melihat raport perkembangan putra/putri Anda.</p>
                    @if($errors->any())
                        <div class="alert alert-danger py-2 text-start"><i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first('credential_key') }}</div>
                    @endif
                    <form action="{{ route('reports.guest.show') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <input type="text" name="credential_key" class="form-control form-control-lg text-center fw-bold text-uppercase" style="letter-spacing:2px;" placeholder="Contoh: TAC-2026-0001" value="{{ old('credential_key') }}" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-unlock me-1"></i> Lihat Raport</button>
                    </form>
                </div>
            </div>
        @else
            @php
                $initial = strtoupper(mb_substr($report->student->name ?? '?', 0, 1));
            @endphp
            <div class="card report-card">
                <div class="report-hero">
                    <div class="hero-avatar">{{ $initial }}</div>
                    <div class="hero-main">
                        <h4>{{ $report->student->name }}</h4>
                        <div class="hero-sub"><i class="bi bi-calendar3 me-1"></i>{{ $report->period_start->format('d M Y') }} — {{ $report->period_end->format('d M Y') }}</div>
                        <div class="hero-meta">
                            @forelse($report->student->classes as $class)
                                <span class="class-chip"><i class="bi bi-easel2"></i>{{ $class->class_category }}</span>
                            @empty
                                <span class="class-chip"><i class="bi bi-easel2"></i>Belum ada kelas</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="report-section">
                    <h6><i class="bi bi-graph-up-arrow"></i> Perkembangan</h6>
                    <p>{{ $report->activity_notes }}</p>
                </div>

                @if($report->tutor_notes)
                    <div class="report-section">
                        <h6><i class="bi bi-chat-square-quote"></i> Catatan Tutor</h6>
                        <p>{{ $report->tutor_notes }}</p>
                    </div>
                @endif

                {{-- Galeri karya sepanjang periode raport. Ikut tertahan bersama
                     raportnya bila muridnya menunggak — akses keduanya satu pintu. --}}
                @if($artworks->isNotEmpty())
                    <div class="report-section">
                        <h6><i class="bi bi-images"></i> Karya Periode Ini ({{ $artworks->count() }})</h6>
                        <div class="artwork-grid">
                            @foreach($artworks as $artwork)
                                <figure class="artwork-item">
                                    <img src="{{ $artwork->photoUrl() }}" alt="{{ $artwork->description ?: 'Karya '.$report->student->name }}" loading="lazy">
                                    <figcaption>
                                        <span class="artwork-date">{{ $artwork->taken_on->format('d M Y') }}</span>
                                        @if($artwork->description)
                                            <span class="artwork-desc">{{ $artwork->description }}</span>
                                        @endif
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="report-section text-center no-print d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
                        <i class="bi bi-printer"></i> Cetak / Simpan PDF
                    </button>
                    <a href="{{ route('reports.guest') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-left"></i> Cek Raport Lain
                    </a>
                </div>
            </div>
        @endif

        <p class="text-center text-white-50 small mt-4 mb-0">&copy; {{ date('Y') }} Tarakan Art Class</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const toggle = document.getElementById('themeToggle');
            const icon = document.getElementById('themeIcon');
            function syncIcon() {
                const theme = document.documentElement.getAttribute('data-bs-theme');
                icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            }
            syncIcon();
            toggle.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-bs-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                localStorage.setItem('tac-theme', next);
                syncIcon();
            });
        })();
    </script>
</body>
</html>
