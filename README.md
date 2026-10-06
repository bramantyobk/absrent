# ABSRENT

Aplikasi web sewa kendaraan (motor/mobil). Stack: Laravel 13, Bootstrap 5.3, Vite, Pest.

> Status: prototipe. Saat ini sudah tersedia **autentikasi (register/login/logout) dan pembatasan akses per role**. Fitur lain (kendaraan, pesanan, dashboard) menyusul.

## Kebutuhan

- PHP >= 8.3 (ekstensi: mbstring, pdo_mysql, fileinfo, gd)
- Composer
- Node.js + npm
- MySQL/MariaDB (atau SQLite untuk uji coba cepat)

## Instalasi

1. Clone repo dan pindah ke branch `dev`:

    ```bash
    git clone git@github.com:bramantyobk/absrent.git
    cd absrent
    git checkout dev
    ```

2. Install dependensi:

    ```bash
    composer install
    npm install
    ```

3. Siapkan file `.env`. Ambil file `.env` dari Google Drive tim, lalu taruh di root proyek.

4. Buat `APP_KEY` (lewati jika `.env` dari Drive sudah berisi `APP_KEY`):

    ```bash
    php artisan key:generate
    ```

5. Sesuaikan koneksi database di `.env` dengan MySQL lokal Anda. `DB_USERNAME` dan `DB_PASSWORD` berbeda di tiap komputer, jadi ubah bila perlu:

    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=absrent
    DB_USERNAME=<user-mysql-anda>
    DB_PASSWORD=<password-mysql-anda>
    ```

6. Buat database kosong bernama `absrent`:

    ```bash
    mysql -u <user-mysql-anda> -p -e "CREATE DATABASE absrent CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    ```

7. Jalankan migrasi dan seeder:

    ```bash
    php artisan migrate --seed
    ```

## Menjalankan

```bash
composer run dev
```

Buka `http://localhost:8000`. Bila tampilan tidak termuat (error Vite manifest), jalankan `npm run build` atau pastikan `composer run dev` aktif.

## Seeder

`php artisan migrate --seed` menjalankan `DatabaseSeeder`, yang berisi:

| Seeder                 | Isi                                                                        |
| ---------------------- | -------------------------------------------------------------------------- |
| `UserSeeder`           | 1 admin, 3 operator, 1 user demo, 5 user acak                              |
| `CompanyProfileSeeder` | Data perusahaan + nomor WhatsApp support                                   |
| `BankAccountSeeder`    | 4 rekening bank (BCA, Mandiri, BRI aktif; BNI nonaktif)                    |
| `VehicleSeeder`        | 12 kendaraan (6 motor, 5 mobil, 1 tidak tersedia)                          |
| `OrderSeeder`          | Pesanan contoh dengan berbagai status, transaksi, dan pengajuan pembatalan |

Data kendaraan, user acak, dan pesanan dibuat acak (Faker), jadi isinya berbeda di tiap mesin.

Untuk mengulang dari nol (menghapus semua data):

```bash
php artisan migrate:fresh --seed
```

> Jangan menjalankan `php artisan db:seed` dua kali pada database yang sama. Email akun seeder bersifat unik, sehingga seeder akan gagal.

## Akun Default (hanya untuk development)

Semua akun memakai password **`password`**.

| Role     | Email                    | Akses                                             |
| -------- | ------------------------ | ------------------------------------------------- |
| Admin    | `admin@absrent.test`     | Dashboard + seluruh fitur pengelolaan             |
| Operator | `operator1@absrent.test` | Dashboard (status bertugas: aktif)                |
| Operator | `operator2@absrent.test` | Dashboard (status bertugas: aktif)                |
| Operator | `operator3@absrent.test` | Dashboard (status bertugas: tidak aktif)          |
| User     | `user@absrent.test`      | Situs pelanggan (tidak bisa membuka `/dashboard`) |

> **Peringatan:** akun dan password ini hanya untuk lingkungan lokal.

## Sistem Role

| Role         | Setelah login diarahkan ke | Hak akses                                                                                 |
| ------------ | -------------------------- | ----------------------------------------------------------------------------------------- |
| **User**     | `/` (beranda)              | Register, login, sewa kendaraan, upload bukti, lihat riwayat sendiri                      |
| **Operator** | `/dashboard`               | Dashboard, kelola kendaraan, verifikasi transaksi, ubah status pesanan, proses pembatalan |
| **Admin**    | `/dashboard`               | Semua hak operator, ditambah kelola user, status bertugas operator, dan data perusahaan   |

Aturan autentikasi yang sudah berlaku:

- Register publik **selalu** menghasilkan role `user`. Admin/operator hanya dibuat lewat seeder atau menu kelola user (menyusul).
- Akun dengan `is_active = false` tidak bisa login, dan sesi yang sedang berjalan ditolak (403) di `/dashboard`.
- User biasa yang membuka `/dashboard` mendapat 403. Tamu diarahkan ke `/login`.
- Login dibatasi 6 percobaan per menit.
- Format nomor WhatsApp dinormalisasi ke `62...` (contoh input `081234567890`).

## Catatan push git

Alur kerja tim: `main` untuk rilis, `dev` sebagai branch gabungan. Jangan commit langsung ke `dev`, kerjakan di branch sendiri lalu ajukan Pull Request ke `dev`.

> Contoh branch sendiri pakai format `<nama>-<fitur>`.

1. Ambil `dev` terbaru:

    ```bash
    git checkout dev
    git pull origin dev
    ```

2. Buat branch kerja dari `dev` (sebelum mengubah kode):

    ```bash
    git checkout -b bram-login
    ```

3. Cek file yang berubah. Pastikan `.env` tidak muncul:

    ```bash
    git status
    ```

4. Rapikan format kode dan jalankan tes. Perbaiki bila ada yang gagal:

    ```bash
    vendor/bin/pint --format agent
    php artisan test --compact
    ```

5. Commit dengan pesan yang jelas (`feat:`, `fix:`, `docs:`, `chore:`):

    ```bash
    git add <file-yang-diubah>
    git commit -m "feat: add login and register page"
    ```

6. Push branch ke GitHub contoh:

    ```bash
    git push -u origin bram-login
    ```

7. Buat Pull Request dengan target branch `dev` (bukan `main`). Pilih salah satu:
    - **GitHub CLI** (butuh `gh` terpasang dan sudah login lewat `gh auth login`):

        ```bash
        gh pr create --base dev --title "feat: add login and register page" --body "Deskripsi singkat perubahan"
        ```

    - **Web GitHub:** buka repo, klik **Compare & pull request**, pastikan _base_ adalah `dev`.

8. Konfirmasi ke grup WA bahwa push sudah dilakukan.
