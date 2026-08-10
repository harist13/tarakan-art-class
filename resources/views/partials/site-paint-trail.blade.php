{{--
    Jejak cat yang mengikuti kursor — dipasang di section hero tiap halaman.
    Sertakan partial ini sebagai anak langsung section, lalu beri section
    tersebut kelas .tac-paint-area dan atribut data-tac-paint.

    Lapisannya dibiarkan kosong dari server; tetesan cat dibuat oleh skrip di
    bawah. @once memastikan skripnya cuma sekali di halaman meski partial ini
    dipakai lebih dari satu section.
--}}
<div class="tac-paint-layer" data-tac-paint-layer aria-hidden="true"></div>

@once
@push('scripts')
<script>
// Kuas mengikuti kursor: tiap gerakan meninggalkan tetesan cat yang memudar,
// warnanya berganti berkala seperti mencelup kuas ke warna lain di palet.
(function () {
    var areas = document.querySelectorAll('[data-tac-paint]');
    if (!areas.length) return;

    // Hanya untuk penunjuk presisi. Di layar sentuh, pointermove baru terkirim
    // saat jari menyeret — jejaknya akan muncul justru ketika orang menggulir.
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var COLORS = ['#F26C5A', '#FFC24B', '#5EA3DE', '#7FB894', '#9A86D6'];
    var MIN_GAP = 26;          // jarak minimal antar tetesan, px
    var DABS_PER_COLOR = 7;    // berapa tetesan sebelum ganti warna
    var LIFETIME = 900;        // ms sampai tetesan hilang
    var MAX_LIVE = 45;         // pagar aman agar simpul tidak menumpuk

    Array.prototype.forEach.call(areas, function (area) {
        var layer = area.querySelector('[data-tac-paint-layer]');
        if (!layer) return;

        var last = null;
        var colorIndex = 0;
        var sinceSwitch = 0;
        var live = 0;

        area.addEventListener('pointermove', function (event) {
            if (event.pointerType !== 'mouse') return;
            if (live >= MAX_LIVE) return;

            // Diukur ulang tiap gerakan: posisi section bergeser saat halaman
            // digulir atau jendela diubah ukurannya.
            var rect = layer.getBoundingClientRect();
            var x = event.clientX - rect.left;
            var y = event.clientY - rect.top;

            // Beri jarak antar tetesan supaya terbaca sebagai sapuan kuas,
            // bukan satu blok cat pekat saat kursor digerakkan pelan.
            if (last) {
                var dx = x - last.x;
                var dy = y - last.y;
                if (dx * dx + dy * dy < MIN_GAP * MIN_GAP) return;
            }
            last = { x: x, y: y };

            var size = 18 + Math.random() * 16;
            var dab = document.createElement('span');
            dab.className = 'tac-paint-dab';
            dab.style.left = (x - size / 2) + 'px';
            dab.style.top = (y - size / 2) + 'px';
            dab.style.width = size + 'px';
            dab.style.height = size + 'px';
            dab.style.backgroundColor = COLORS[colorIndex];
            layer.appendChild(dab);
            live++;

            var anim = dab.animate([
                { transform: 'scale(0.25)', opacity: 0.75 },
                { transform: 'scale(1)',    opacity: 0.6, offset: 0.3 },
                { transform: 'scale(0.85)', opacity: 0 }
            ], { duration: LIFETIME, easing: 'cubic-bezier(0.2, 0.7, 0.3, 1)', fill: 'forwards' });

            anim.onfinish = function () {
                dab.remove();
                live--;
            };

            if (++sinceSwitch >= DABS_PER_COLOR) {
                sinceSwitch = 0;
                colorIndex = (colorIndex + 1) % COLORS.length;
            }
        });

        // Kursor keluar lalu masuk lagi dari sisi lain: jangan sampai jarak
        // lompatannya menahan tetesan pertama.
        area.addEventListener('pointerleave', function () { last = null; });
    });
})();
</script>
@endpush
@endonce
