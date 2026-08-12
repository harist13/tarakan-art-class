<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kebijakan Pembayaran
    |--------------------------------------------------------------------------
    |
    | Gerbang uang di aplikasi ini bertingkat, bukan sekali potong:
    |
    |   invoice terbit (unpaid)  → normal, tidak menghalangi apa pun
    |   lewat due_date           → MENUNGGAK: ditandai di layar, raport & kelas
    |                              pengganti ditahan, absensi TETAP jalan
    |   lewat due_date + grace   → murid di-suspend: tidak lagi masuk daftar
    |                              kelas berikutnya, tapi histori tetap utuh
    |
    | Absensi sengaja tidak pernah digerbang. Kelas ini tatap muka — kalau anak
    | datang, kehadirannya harus bisa dicatat apa pun status tagihannya.
    |
    */

    'payment' => [

        // Jarak hari dari tanggal invoice ke jatuh tempo, dipakai sebagai
        // nilai awal kolom "Jatuh Tempo" di form pembayaran.
        'due_days' => (int) env('PAYMENT_DUE_DAYS', 7),

        // Masa toleransi setelah jatuh tempo sebelum murid di-suspend otomatis.
        'grace_days' => (int) env('PAYMENT_GRACE_DAYS', 14),

    ],

];
