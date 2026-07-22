<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    // Light / Dark theme toggle
    (function () {
        const toggle = document.getElementById('themeToggle');
        const icon = toggle.querySelector('i');

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

    // Live search: auto-submit form filter [data-live] tanpa klik tombol.
    (function () {
        const FOCUS_KEY = 'tac-live-focus';

        function submitForm(form) {
            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        function remember(input) {
            try {
                sessionStorage.setItem(FOCUS_KEY, JSON.stringify({
                    path: location.pathname,
                    name: input.name,
                }));
            } catch (e) { /* abaikan */ }
        }

        document.querySelectorAll('form[data-live]').forEach(function (form) {
            // Input teks → debounce 400ms agar tidak submit tiap ketikan.
            form.querySelectorAll('input[type="text"], input[type="search"]').forEach(function (input) {
                let timer;
                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    remember(input);
                    timer = setTimeout(function () { submitForm(form); }, 400);
                });
            });

            // Select & tanggal → submit langsung saat berubah.
            form.querySelectorAll('select, input[type="date"], input[type="month"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    remember(input);
                    submitForm(form);
                });
            });
        });

        // Kembalikan fokus ke input pencarian setelah halaman reload.
        let saved = null;
        try { saved = JSON.parse(sessionStorage.getItem(FOCUS_KEY)); } catch (e) { /* abaikan */ }
        if (saved && saved.path === location.pathname) {
            const el = document.querySelector('form[data-live] [name="' + saved.name + '"]');
            if (el && (el.type === 'text' || el.type === 'search')) {
                el.focus();
                const val = el.value;
                el.value = '';
                el.value = val; // pindahkan kursor ke akhir teks
            }
            sessionStorage.removeItem(FOCUS_KEY);
        }
    })();
</script>
