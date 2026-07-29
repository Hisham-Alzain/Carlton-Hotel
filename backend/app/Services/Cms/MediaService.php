<?php

namespace App\Services\Cms;

use App\Exceptions\NotFoundException;
use App\Models\Media;
use App\Traits\FileTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MediaService
{
    use FileTrait;

    public function attach(Model $model, UploadedFile $file, int $sortOrder = 0): array
    {
        $dir  = 'cms/' . class_basename($model) . '/' . $model->uuid;
        $path = $this->storeFile($file, $dir);

        $media = DB::transaction(fn () => Media::create([
            'mediable_type' => $model->getMorphClass(),
            'mediable_id'   => $model->id,
            'disk'          => 'public',
            'path'          => $path,
            'file_name'     => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'sort_order'    => $sortOrder,
        ]));

        return ['data' => $media, 'code' => 201];
    }

    /**
     * Delete media that belongs to $parent.
     *
     * The nested delete routes bind {parent} and {media} independently, so
     * without this check any cms.edit holder could delete any media row
     * through any parent's URL (e.g. a promotion's image via a room-type
     * route). 404 rather than 403: from the caller's perspective that media
     * does not exist under that parent, and it leaks nothing about other
     * entities' assets.
     *
     * Both sides use `getMorphClass()`, never `get_class()`: `Relation::morphMap()`
     * already aliases four models, and the moment a media-bearing model joins
     * that map the stored `mediable_type` becomes the alias while `get_class()`
     * keeps returning the FQCN — every legitimate delete would 404.
     */
    public function destroy(Model $parent, Media $media): array
    {
        if ($media->mediable_type !== $parent->getMorphClass() || (int) $media->mediable_id !== (int) $parent->getKey()) {
            throw new NotFoundException(__('custom.errors.not_found'), [
                'media'  => $media->uuid,
                'parent' => class_basename($parent),
            ]);
        }

        DB::transaction(function () use ($media) {
            $this->deleteFile($media->path, $media->disk);
            $media->delete();
        });
        return ['data' => null, 'code' => 204];
    }
}
