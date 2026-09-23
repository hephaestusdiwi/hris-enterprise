<?php

namespace App\Modules\Grooming\Concerns;

use App\Modules\Grooming\Exceptions\GroomingValidationException;
use Illuminate\Support\Facades\Storage;

trait SavesGroomingPhoto
{
    /**
     * Simpan foto base64 sebagai webp — pola identik dengan
     * AttendanceService::resolveAndSavePhoto(), disk public. Dipakai
     * bersama oleh GroomingSelfService (1 foto per submission) dan
     * GroomingStoreService (1 foto per item, bisa lebih dari satu per
     * submission — makanya nama file pakai uniqid, bukan cuma timestamp,
     * biar tidak saling timpa kalau beberapa item disubmit di detik sama).
     */
    private function saveGroomingPhoto(string $photoBase64, string $pathPrefix): string
    {
        $raw = preg_replace('/^data:image\/\w+;base64,/', '', $photoBase64);
        $decoded = base64_decode($raw);
        $image = @imagecreatefromstring($decoded);

        if (! $image) {
            throw new GroomingValidationException('Format foto tidak valid.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $filename = "{$pathPrefix}/".now()->timestamp.'-'.uniqid().'.webp';
        Storage::disk('public')->makeDirectory(dirname($filename));
        imagewebp($image, Storage::disk('public')->path($filename), 85);
        imagedestroy($image);

        return $filename;
    }
}