<?php

namespace Database\Seeders\Support;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Copies the Flutter app's real artwork (`mobile/assets/images`) onto the public
 * disk and attaches it through a model's `images()` morphMany, so a seeded API
 * serves the same photographs the mobile demo screens render.
 *
 * Unlike GeneratesPlaceholderMedia this depends on the sibling `mobile/`
 * checkout being present. A missing file is warned about and skipped rather
 * than thrown, so a backend-only environment still seeds to completion — the
 * records exist, only the photo is absent.
 */
trait CopiesMobileAssetImages
{
    private const MIME_BY_EXTENSION = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /** Absolute path of the Flutter image folder, resolved from the monorepo root. */
    protected function mobileImagesPath(): string
    {
        return base_path('../mobile/assets/images');
    }

    /**
     * Replace a model's gallery with the given asset files, in order.
     *
     * @param array<int, string> $fileNames file names relative to mobile/assets/images
     */
    protected function attachAssetImages(Model $model, array $fileNames): void
    {
        // Re-running the seeder replaces the gallery instead of stacking
        // duplicate rows on top of the previous run.
        $this->purgeImages($model);

        foreach (array_values($fileNames) as $sortOrder => $fileName) {
            $this->attachAssetImage($model, $fileName, $sortOrder);
        }
    }

    protected function attachAssetImage(Model $model, string $fileName, int $sortOrder = 0): ?Media
    {
        $source = $this->mobileImagesPath() . '/' . $fileName;

        if (! is_file($source)) {
            $this->command?->warn("  ! mobile asset not found, skipped: {$fileName}");

            return null;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $directory = 'cms/' . class_basename($model) . '/' . $model->uuid;
        $path      = $directory . '/' . $fileName;

        Storage::disk('public')->put($path, (string) file_get_contents($source));

        return $model->images()->create([
            'disk'       => 'public',
            'path'       => $path,
            'file_name'  => $fileName,
            'mime_type'  => self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream',
            'size'       => (int) filesize($source),
            'sort_order' => $sortOrder,
        ]);
    }

    /** Drop a model's media rows and the files behind them. */
    protected function purgeImages(Model $model): void
    {
        foreach ($model->images()->get() as $media) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }
    }
}
