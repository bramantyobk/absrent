<?php

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
    | oleh scheduler (fase 8). Nilainya disimpan di orders.expires_at saat dibuat.
    |
    */

    'pending_expiry_hours' => (int) env('RENTAL_PENDING_EXPIRY_HOURS', 24),

];
