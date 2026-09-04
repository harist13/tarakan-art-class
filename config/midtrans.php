<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Midtrans Snap
    |--------------------------------------------------------------------------
    |
    | Kunci diambil dari Dashboard Midtrans → Settings → Access Keys. Sandbox
    | dan Production punya pasangan kunci yang BERBEDA; jangan menyalakan
    | MIDTRANS_IS_PRODUCTION sebelum kunci production dipasang.
    |
    | Selama server_key kosong, integrasi dianggap mati: tautan pembayaran tidak
    | ikut dikirim di WhatsApp dan halaman /bayar menolak dengan pesan yang
    | jelas — jadi aplikasi tetap jalan seperti sebelumnya (konfirmasi manual).
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),

    /*
    | Masa berlaku tautan pembayaran. Dipakai sebagai `expiry` di Snap sekaligus
    | penentu kapan token lama boleh dibuat ulang dari sisi kita.
    */
    'expiry_hours' => (int) env('MIDTRANS_EXPIRY_HOURS', 24),

    /*
    | Channel yang boleh muncul di popup Snap (daftar putih `enabled_payments`).
    |
    | QRIS dan dompet digital sengaja TIDAK didaftarkan. Transaksinya tidak bisa
    | ditelusuri lewat order_id di Core API, jadi tombol "cek status" tidak
    | menolong: invoice hanya bisa lunas kalau notifikasi Midtrans sampai. Begitu
    | webhook meleset sekali saja, uang sudah masuk tapi invoicenya tertinggal
    | Unpaid dan tidak ada cara membetulkannya dari dalam aplikasi. Virtual
    | Account selalu bisa diperiksa ulang lewat order_id.
    |
    | Kartu kredit serta gerai ritel (Indomaret/Alfamart) juga tidak ditawarkan:
    | biayanya paling mahal dan hampir tidak pernah dipakai orang tua untuk SPP
    | bulanan. Yang tersisa hanyalah Virtual Account seluruh bank.
    |
    | Kosongkan (MIDTRANS_ENABLED_PAYMENTS=) untuk kembali menampilkan seluruh
    | channel yang aktif di Dashboard Midtrans, QRIS termasuk.
    */
    'enabled_payments' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'MIDTRANS_ENABLED_PAYMENTS',
            'bca_va,bni_va,bri_va,cimb_va,permata_va,other_va,echannel'
        ))
    ))),

    /*
    | Endpoint resmi Midtrans. Snap dipakai untuk membuat transaksi, Core API
    | untuk mengambil ulang status (dipakai saat webhook tidak sampai).
    */
    'endpoints' => [
        'sandbox' => [
            'snap' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
            'api' => 'https://api.sandbox.midtrans.com/v2',
            'snap_js' => 'https://app.sandbox.midtrans.com/snap/snap.js',
        ],
        'production' => [
            'snap' => 'https://app.midtrans.com/snap/v1/transactions',
            'api' => 'https://api.midtrans.com/v2',
            'snap_js' => 'https://app.midtrans.com/snap/snap.js',
        ],
    ],

];
