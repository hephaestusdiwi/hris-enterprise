<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeePhotoController extends Controller
{
    private const MAX_DIMENSION = 800;

    public function upload(Request $request, Employee $employee)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $file = $request->file('photo');

        $image = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => null,
        };

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => 'Format foto tidak didukung.',
                'data' => null,
            ], 422);
        }

        // Preserve transparansi PNG supaya nggak jadi background hitam pas di-convert ke WebP
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $image = $this->resizeIfNeeded($image);

        // Hapus foto lama dulu (kalau ada) sebelum simpan yang baru
        if ($employee->photo_path && Storage::disk('public')->exists($employee->photo_path)) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $filename = 'employees/'.$employee->id.'/photo-'.now()->timestamp.'.webp';
        Storage::disk('public')->makeDirectory('employees/'.$employee->id);

        imagewebp($image, Storage::disk('public')->path($filename), 85);
        imagedestroy($image);

        $employee->update(['photo_path' => $filename]);

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui',
            'data' => [
                'photo_path' => $filename,
                'photo_url' => Storage::disk('public')->url($filename),
            ],
        ]);
    }

    public function destroy(Employee $employee)
    {
        if ($employee->photo_path && Storage::disk('public')->exists($employee->photo_path)) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $employee->update(['photo_path' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil dihapus',
            'data' => null,
        ]);
    }

    private function resizeIfNeeded(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
            return $image;
        }

        $ratio = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }
}
