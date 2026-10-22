<?php

namespace App\Services;

use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan foto kendaraan di disk publik. Gambar diperkecil dan dikompres (JPEG) agar hemat disk shared hosting.
 * Foto profil memakai logika yang sama dengan direktori dan lebar maksimal sendiri (`ProfilePhotoStorage`).
 */
class VehiclePhotoStorage
{
    public const DISK = 'public';

    protected const DIRECTORY = 'vehicles';

    protected const MAX_WIDTH = 1200;

    protected const QUALITY = 80;

    /**
     * @return string path relatif di disk publik
     *
     * @throws ValidationException jika file tidak dapat dibaca sebagai gambar
     */
    public function store(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => 'Foto tidak dapat diproses. Gunakan file JPG, PNG, atau WebP lain.']);
        }

        $source = $this->applyExifOrientation($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min($width, static::MAX_WIDTH);
        $targetHeight = (int) round($height * $targetWidth / $width);

        // Kanvas putih agar PNG/WebP transparan tidak menjadi hitam saat jadi JPEG
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, static::QUALITY);
        $contents = (string) ob_get_clean();

        $path = static::DIRECTORY.'/'.str()->uuid().'.jpg';
        $this->disk()->put($path, $contents);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    private function applyExifOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
