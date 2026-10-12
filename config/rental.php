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

];
