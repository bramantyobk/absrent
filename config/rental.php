<?php

/*
| Nilai bawaan aturan sewa. Sebagian besar dapat diganti admin lewat menu Pengaturan Sewa (tabel rental_settings),
| yang menimpa nilai di sini saat aplikasi mulai (App\Services\RentalSettings).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Jeda pengembalian (jam)
    |--------------------------------------------------------------------------
    |
    | Setiap pesanan menahan kendaraan sampai end_at ditambah jeda ini sebagai
    | toleransi keterlambatan. Jeda dihitung saat pengecekan, tidak disimpan.
    |
    */

    'return_buffer_hours' => (int) env('RENTAL_RETURN_BUFFER_HOURS', 3),

    /*
    |--------------------------------------------------------------------------
    | Durasi minimum jendela kosong (jam)
    |--------------------------------------------------------------------------
    |
    | Celah di antara jadwal yang lebih pendek dari ini tidak ditampilkan
    | sebagai jendela kosong pada detail kendaraan.
    |
    */

    'min_free_window_hours' => (int) env('RENTAL_MIN_FREE_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Durasi sewa
    |--------------------------------------------------------------------------
    |
    | Durasi minimal (jam) dan maksimal (hari) satu item sewa. Batas maksimal
    | dapat diganti per kendaraan lewat kolom vehicles.max_rental_days.
    |
    */

    'min_rental_hours' => (int) env('RENTAL_MIN_RENTAL_HOURS', 24),

    'max_rental_days' => (int) env('RENTAL_MAX_RENTAL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Kedaluwarsa pesanan Pending (jam)
    |--------------------------------------------------------------------------
    |
    | Pesanan Pending yang tidak dikonfirmasi dalam batas ini dibatalkan otomatis
    | oleh scheduler (`orders:expire`). Nilainya disimpan di orders.expires_at saat dibuat.
    |
    */

    'pending_expiry_hours' => (int) env('RENTAL_PENDING_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Batas verifikasi pembayaran (jam)
    |--------------------------------------------------------------------------
    |
    | Transaksi Menunggu Verifikasi yang tidak diverifikasi staf dalam batas ini
    | sejak konfirmasi menjadi Gagal otomatis oleh scheduler. Nilainya disimpan
    | di orders.expires_at saat pelanggan mengonfirmasi pembayaran.
    |
    */

    'confirm_validation_window_hours' => (int) env('RENTAL_CONFIRM_VALIDATION_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Peringatan verifikasi hampir habis (jam)
    |--------------------------------------------------------------------------
    |
    | Transaksi Menunggu Verifikasi yang batas verifikasinya kurang dari ini
    | ditandai "Hampir habis" di dashboard agar segera diperiksa staf.
    |
    */

    'verification_warning_hours' => (int) env('RENTAL_VERIFICATION_WARNING_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Batas pengurangan biaya ganti kendaraan untuk operator (rupiah)
    |--------------------------------------------------------------------------
    |
    | Pengurangan biaya di bawah batas ini dapat dilakukan operator atau admin.
    | Pengurangan sebesar batas ini atau lebih hanya dapat dilakukan admin.
    |
    */

    'vehicle_change_admin_threshold' => (int) env('RENTAL_VEHICLE_CHANGE_ADMIN_THRESHOLD', 100000),

    /*
    |--------------------------------------------------------------------------
    | Batas pesanan Pending per pelanggan
    |--------------------------------------------------------------------------
    |
    | Pesanan Pending menahan jadwal kendaraan. Batas ini mencegah satu
    | pelanggan memblokir banyak unit dengan pesanan yang belum dikonfirmasi.
    | Pesanan Menunggu Verifikasi (sudah dikonfirmasi, belum diverifikasi) ikut dihitung.
    |
    */

    'max_pending_orders' => (int) env('RENTAL_MAX_PENDING_ORDERS', 2),

    /*
    |--------------------------------------------------------------------------
    | Tunggu kunci idempotensi checkout (detik)
    |--------------------------------------------------------------------------
    |
    | Lama menunggu bila permintaan dengan Idempotency-Key yang sama masih diproses.
    | Lewat dari ini, klien menerima kesalahan validasi dan boleh mencoba lagi.
    |
    */

    'checkout_lock_wait_seconds' => (int) env('RENTAL_CHECKOUT_LOCK_WAIT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Maksimal kendaraan per pesanan
    |--------------------------------------------------------------------------
    |
    | Satu pesanan memakai satu periode (waktu mulai dan durasi yang sama) untuk
    | semua kendaraannya. Kendaraan tambahan ditambah satu kali di halaman pesanan.
    |
    */

    'max_vehicles_per_order' => (int) env('RENTAL_MAX_VEHICLES_PER_ORDER', 2),

    /*
    |--------------------------------------------------------------------------
    | Label zona waktu
    |--------------------------------------------------------------------------
    |
    | Semua waktu sewa mengikuti lokasi perusahaan (zona aplikasi) dan diberi
    | label ini di UI.
    |
    */

    'timezone_label' => env('RENTAL_TIMEZONE_LABEL', 'WIB'),

];
