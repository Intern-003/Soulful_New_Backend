<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageUploadService
{
    public static function uploadWebp(
        $image,
        $folder = 'uploads',
        $width = 1200,
        $quality = 80
    ) {
        try {

            // Generate unique filename
            $filename = Str::uuid() . '.webp';

            // Folder path
            $folderPath = public_path("uploads/{$folder}");

            // Create directory if not exists
            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true, true);
            }

            // Read image
            $img = Image::read($image);

            // Resize large images only
            if ($img->width() > $width) {

                $img->resize($width, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Save full path
            $fullPath = $folderPath . '/' . $filename;

            // Convert and save as WEBP
            $img->toWebp($quality)->save($fullPath);

            // Return relative path
            return "uploads/{$folder}/{$filename}";
        } catch (Exception $e) {

            Log::error('Image Upload Failed', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Delete old image
     */
    public static function deleteImage($path)
    {
        $fullPath = public_path($path);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}