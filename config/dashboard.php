<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Dashboard Admin
|--------------------------------------------------------------------------
| Pilihan tampilan dashboard yang bisa berubah tanpa menyentuh Blade atau
| controller. Angka operasional tetap dihitung live dari database.
*/

return [

    /*
    |----------------------------------------------------------------------
    | Grafik pertumbuhan murid
    |----------------------------------------------------------------------
    |
    | Kelas ini baru berjalan beberapa bulan, jadi jendela 12 bulan penuh
    | menyisakan separuh grafik kosong. Mode `auto` memangkas bulan-bulan
    | sebelum murid pertama bergabung; mode `full` tetap menampilkan jendela
    | penuh untuk perbandingan tahun-ke-tahun begitu datanya sudah cukup.
    |
    */

    'growth_chart' => [

        // 'auto' → mulai dari sekitar murid pertama bergabung.
        // 'full' → selalu tampilkan `months` bulan terakhir.
        'range' => env('DASHBOARD_GROWTH_RANGE', 'auto'),

        // Batas terjauh grafik boleh mundur, juga panjang tetap mode 'full'.
        'months' => (int) env('DASHBOARD_GROWTH_MONTHS', 12),

        // Bulan kosong yang sengaja disisakan sebelum murid pertama, supaya
        // titik nolnya terlihat dan bar pertama punya pembanding.
        'lead_months' => (int) env('DASHBOARD_GROWTH_LEAD_MONTHS', 1),

        // Lebar minimum grafik. Tanpa ini, kelas yang baru buka sebulan akan
        // tampil sebagai satu bar raksasa selebar kartu.
        'min_months' => (int) env('DASHBOARD_GROWTH_MIN_MONTHS', 4),

    ],

];
