<?php

namespace Database\Seeders\Support;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Generates simple labeled JPEG placeholders with GD and attaches them via a
// model's `images()` morphMany relation — so the seeded API responses have
// real, servable image URLs for frontend developers to render, without
// depending on any external asset source.
trait GeneratesPlaceholderMedia
{
    /**
     * @param array<int, string> $labels one photo per label
     */
    protected function attachPhotos(Model $model, array $labels): void
    {
        foreach ($labels as $i => $label) {
            $this->attachPhoto($model, $label, $i);
        }
    }

    protected function attachPhoto(Model $model, string $label, int $sortOrder = 0): Media
    {
        $bytes = $this->renderPlaceholderJpeg($label);
        $dir = 'cms/' . class_basename($model) . '/' . $model->uuid;
        $filename = Str::slug($label) . '-' . Str::random(6) . '.jpg';

        Storage::disk('public')->put($dir . '/' . $filename, $bytes);

        return $model->images()->create([
            'disk'       => 'public',
            'path'       => $dir . '/' . $filename,
            'file_name'  => $filename,
            'mime_type'  => 'image/jpeg',
            'size'       => strlen($bytes),
            'sort_order' => $sortOrder,
        ]);
    }

    private function renderPlaceholderJpeg(string $label, int $width = 1200, int $height = 800): string
    {
        $image = imagecreatetruecolor($width, $height);

        // Deterministic-ish but varied background per label so a gallery
        // doesn't look like one repeated tile.
        $seed = crc32($label);
        $bg = imagecolorallocate($image, 40 + ($seed % 160), 40 + (($seed >> 8) % 160), 40 + (($seed >> 16) % 160));
        imagefill($image, 0, 0, $bg);

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = 5; // largest built-in GD font
        $text = 'Carlton Hotel — ' . $label;
        $textWidth = imagefontwidth($font) * strlen($text);
        $x = max(20, intdiv($width - $textWidth, 2));
        $y = intdiv($height, 2) - 10;
        imagestring($image, $font, $x, $y, $text, $white);
        imagestring($image, 3, $x, $y + 30, 'placeholder image — seeded for API testing', $white);

        ob_start();
        imagejpeg($image, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
