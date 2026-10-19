<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageUploadService
{
    /**
     * Convert an uploaded image to WebP format and store it on disk.
     *
     * @param UploadedFile $file The uploaded image file.
     * @param string $directory Storage subdirectory (e.g., 'inventory', 'memos').
     * @param string $disk The storage disk name (defaults to 'local').
     * @param int $quality WebP quality factor (0-100, default 80).
     * @return string Stored relative file path.
     * @throws RuntimeException If WebP conversion is unsupported or fails.
     */
    public function storeAsWebp(
        UploadedFile $file,
        string $directory = 'inventory',
        string $disk = 'local',
        int $quality = 80
    ): string {
        if (!function_exists('imagewebp')) {
            throw new RuntimeException('PHP GD extension does not have WebP support enabled.');
        }

        $rawContent = file_get_contents($file->getRealPath());
        if ($rawContent === false) {
            throw new RuntimeException('Unable to read uploaded image file content.');
        }

        $image = @imagecreatefromstring($rawContent);
        if ($image === false) {
            throw new RuntimeException('Failed to process image with GD library.');
        }

        // Preserve alpha transparency for PNGs and transparent WebPs
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $success = imagewebp($image, null, $quality);
        $webpContent = ob_get_clean();
        imagedestroy($image);

        if (!$success || $webpContent === false) {
            throw new RuntimeException('Failed to convert image to WebP format.');
        }

        $filename = Str::random(40) . '.webp';
        $normalizedDirectory = trim($directory, '/');
        $path = $normalizedDirectory ? "{$normalizedDirectory}/{$filename}" : $filename;

        Storage::disk($disk)->put($path, $webpContent);

        return $path;
    }

    /**
     * Store a file, converting it to WebP if it is an image, or saving as-is if it is a document.
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string $disk
     * @param int $quality
     * @return string Stored relative file path.
     */
    public function storeImageOrFile(
        UploadedFile $file,
        string $directory = 'inventory',
        string $disk = 'local',
        int $quality = 80
    ): string {
        $mime = $file->getMimeType() ?? '';

        if (str_starts_with($mime, 'image/')) {
            return $this->storeAsWebp($file, $directory, $disk, $quality);
        }

        return $file->store($directory, $disk);
    }
}
