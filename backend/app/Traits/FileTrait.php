<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileTrait
{
    public function storeFile(UploadedFile $file, string $dir, string $disk = 'public'): string
    {
        return $file->store($dir, $disk);
    }

    public function fileUrl(?string $path, string $disk = 'public'): ?string
    {
        if ($path === null) return null;
        return Storage::disk($disk)->url($path);
    }

    public function deleteFile(?string $path, string $disk = 'public'): void
    {
        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }
}
