<?php

namespace Database\Seeders\Support;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Attaches real photographs to CMS entities, replacing the GD placeholders
 * CmsContentSeeder renders.
 *
 * Two sources, addressed by prefix:
 *   - `web:<file>`    → database/seeders/assets/photos (copied from the
 *                       Carlton-hotel-s web frontend, committed with the backend
 *                       so seeding never depends on that sibling checkout)
 *   - `mobile:<file>` → mobile/assets/images (the Flutter app's own artwork)
 *
 * Every photo is written to the public disk once, under `cms/demo/`, and each
 * placement is its own media row pointing at that shared path — the same shape
 * `MediaService::attachExisting` produces, so `Media::purgeFileIfUnreferenced`
 * keeps the file until the last placement is gone.
 */
trait AttachesDemoPhotos
{
    /** @var array<string, array{path: string, mime: string, size: int}> */
    private array $storedPhotos = [];

    /**
     * A GD placeholder is `<slug>-<6 random chars>.jpg` (GeneratesPlaceholderMedia).
     * The `cms/demo/` guard matters: `web-dining-brunch.jpg` fits the same shape.
     */
    protected function isPlaceholder(Media $media): bool
    {
        return ! str_starts_with($media->path, 'cms/demo/')
            && (bool) preg_match('/-[A-Za-z0-9]{6}\.jpg$/', $media->file_name);
    }

    /**
     * Swap a model's placeholder images for real photos. Real images a
     * previous seeder attached (MobileDemoSeeder's artwork) are kept, and the
     * new photos go after them. With `$appendWhenReal`, a model that has only
     * real images still gets the photos appended, once — re-running never
     * stacks a second copy.
     *
     * @param array<int, string> $photos prefixed photo keys, first is the cover
     */
    protected function replacePlaceholders(Model $model, array $photos, bool $appendWhenReal = false): void
    {
        $images       = $model->images()->get();
        $placeholders = $images->filter(fn (Media $m) => $this->isPlaceholder($m));
        $real         = $images->count() - $placeholders->count();
        $placed       = $images->contains(fn (Media $m) => str_starts_with($m->path, 'cms/demo/'));

        if ($placeholders->isEmpty() && (! $appendWhenReal || $placed)) {
            return;
        }

        foreach ($placeholders as $media) {
            // Unlink directly: the model's deleted hook only queues the unlink,
            // and a database queue nobody is working would leave the files.
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }

        $order = $real;
        foreach ($photos as $photo) {
            $this->placePhoto($model, $photo, $order++);
        }
    }

    protected function placePhoto(Model $model, string $photo, int $sortOrder = 0): ?Media
    {
        $stored = $this->storePhoto($photo);

        if ($stored === null) {
            return null;
        }

        return $model->images()->create([
            'disk'       => 'public',
            'path'       => $stored['path'],
            'file_name'  => basename($stored['path']),
            'mime_type'  => $stored['mime'],
            'size'       => $stored['size'],
            'sort_order' => $sortOrder,
        ]);
    }

    /** @return array{path: string, mime: string, size: int}|null */
    private function storePhoto(string $photo): ?array
    {
        if (isset($this->storedPhotos[$photo])) {
            return $this->storedPhotos[$photo];
        }

        [$source, $file] = explode(':', $photo, 2);
        $absolute = match ($source) {
            'web'    => database_path('seeders/assets/photos/' . $file),
            'mobile' => base_path('../mobile/assets/images/' . $file),
        };

        if (! is_file($absolute)) {
            $this->command?->warn("  ! demo photo not found, skipped: {$photo}");

            return null;
        }

        $path = 'cms/demo/' . $source . '-' . $file;
        Storage::disk('public')->put($path, (string) file_get_contents($absolute));

        return $this->storedPhotos[$photo] = [
            'path' => $path,
            'mime' => str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg',
            'size' => (int) filesize($absolute),
        ];
    }
}
