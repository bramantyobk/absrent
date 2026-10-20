<?php

namespace App\Services;

use App\Models\RentalSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Aturan sewa yang dapat diatur admin. Nilai dari database (kolom yang terisi) dimuat ke `config('rental.*')` saat aplikasi
 * mulai, sehingga kode yang membaca `config()` tidak berubah dan test tetap dapat menimpa nilainya. Kolom kosong memakai nilai
 * bawaan dari `config/rental.php` (diambil sebelum ditimpa). Pengaturan teknis (waktu tunggu kunci checkout, label zona
 * waktu) tidak ikut.
 */
class RentalSettings
{
    public const CACHE_KEY = 'rental-settings';

    /**
     * Kolom yang dapat diatur beserta label, satuan, rentang, dan keterangannya.
     *
     * @var array<string, array{label: string, unit: string, min: int, max: int, help: string}>
     */
    public const FIELDS = [
        'return_buffer_hours' => ['label' => 'Jeda pengembalian', 'unit' => 'jam', 'min' => 0, 'max' => 24, 'help' => 'Setiap pesanan menahan kendaraan sampai waktu kembali ditambah jeda ini sebagai toleransi keterlambatan.'],
        'min_free_window_hours' => ['label' => 'Jendela kosong minimum', 'unit' => 'jam', 'min' => 1, 'max' => 168, 'help' => 'Celah antar jadwal yang lebih pendek dari ini tidak tampil sebagai jendela kosong pada detail kendaraan.'],
        'min_rental_hours' => ['label' => 'Durasi sewa minimal', 'unit' => 'jam', 'min' => 1, 'max' => 168, 'help' => 'Dibulatkan ke atas per 24 jam (hari). Tidak boleh melebihi durasi sewa maksimal.'],
        'max_rental_days' => ['label' => 'Durasi sewa maksimal', 'unit' => 'hari', 'min' => 1, 'max' => 30, 'help' => 'Batas bawaan semua kendaraan. Dapat diganti per kendaraan di detail kendaraan.'],
        'pending_expiry_hours' => ['label' => 'Batas konfirmasi pesanan Pending', 'unit' => 'jam', 'min' => 1, 'max' => 168, 'help' => 'Pesanan yang belum dikonfirmasi pembayarannya dibatalkan otomatis setelah batas ini.'],
        'confirm_validation_window_hours' => ['label' => 'Batas verifikasi pembayaran', 'unit' => 'jam', 'min' => 1, 'max' => 168, 'help' => 'Transaksi Menunggu Verifikasi yang tidak diverifikasi staf dalam batas ini menjadi Gagal otomatis.'],
        'verification_warning_hours' => ['label' => 'Peringatan verifikasi hampir habis', 'unit' => 'jam', 'min' => 1, 'max' => 72, 'help' => 'Transaksi yang sisa waktu verifikasinya kurang dari ini ditandai "Hampir habis" di dashboard. Tidak boleh melebihi batas verifikasi.'],
        'vehicle_change_admin_threshold' => ['label' => 'Batas pengurangan biaya ganti kendaraan untuk operator', 'unit' => 'rupiah', 'min' => 0, 'max' => 100000000, 'help' => 'Pengurangan biaya sebesar batas ini atau lebih hanya dapat dilakukan admin.'],
        'max_pending_orders' => ['label' => 'Maksimal pesanan Pending per pelanggan', 'unit' => 'pesanan', 'min' => 1, 'max' => 10, 'help' => 'Pesanan yang sudah dikonfirmasi tetapi belum diverifikasi tetap dihitung.'],
        'max_vehicles_per_order' => ['label' => 'Maksimal kendaraan per pesanan', 'unit' => 'kendaraan', 'min' => 1, 'max' => 5, 'help' => 'Semua kendaraan dalam satu pesanan memakai periode yang sama.'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $defaults = null;

    /**
     * Memuat nilai dari database ke `config('rental.*')`. Aman dipanggil saat tabel belum ada (mis. saat `migrate`).
     */
    public function apply(): void
    {
        $this->defaults ??= $this->snapshotDefaults();

        try {
            $overrides = Cache::get(self::CACHE_KEY);

            if ($overrides === null) {
                $overrides = $this->load();
                Cache::forever(self::CACHE_KEY, $overrides);
            }
        } catch (QueryException) {
            return;
        }

        foreach ($overrides as $key => $value) {
            config(["rental.{$key}" => $value]);
        }
    }

    /**
     * Membersihkan cache lalu memuat ulang, dipanggil setelah pengaturan disimpan.
     */
    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);

        foreach ($this->defaults() as $key => $value) {
            config(["rental.{$key}" => $value]);
        }

        $this->apply();
    }

    /**
     * Nilai bawaan dari `config/rental.php`.
     *
     * @return array<string, int>
     */
    public function defaults(): array
    {
        return $this->defaults ??= $this->snapshotDefaults();
    }

    /**
     * Nilai yang berlaku saat ini.
     *
     * @return array<string, int>
     */
    public function current(): array
    {
        return array_map(fn (string $key): int => (int) config("rental.{$key}"), array_combine(array_keys(self::FIELDS), array_keys(self::FIELDS)));
    }

    /**
     * @return array<string, int>
     */
    private function load(): array
    {
        $row = RentalSetting::query()->orderBy('id')->first();

        if ($row === null) {
            return [];
        }

        return collect(array_keys(self::FIELDS))
            ->mapWithKeys(fn (string $key): array => [$key => $row->{$key}])
            ->filter(fn (mixed $value): bool => $value !== null)
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function snapshotDefaults(): array
    {
        return array_map(fn (string $key): int => (int) config("rental.{$key}"), array_combine(array_keys(self::FIELDS), array_keys(self::FIELDS)));
    }
}
