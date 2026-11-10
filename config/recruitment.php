<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin Awal
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh seeder produksi untuk membuat Super Admin pertama. Jika email
    | atau password kosong, seeder melewati langkah ini. Akun yang sudah ada
    | tidak diubah, sehingga seeder aman dijalankan berulang.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Tampilan
    |--------------------------------------------------------------------------
    |
    | Semua waktu disimpan dalam UTC dan dikonversi ke zona ini saat ditampilkan.
    |
    */

    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Nilai Awal Pengaturan Sistem
    |--------------------------------------------------------------------------
    |
    | Ditulis ke tabel system_settings saat seeding pertama. Setelah itu nilai
    | dikelola Super Admin dari halaman konfigurasi sistem.
    |
    */

    'defaults' => [
        'consent_policy_version' => '2026-10',
        'retention_months' => 12,
    ],

];
