<?php

namespace App\Services\Cms;

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
            'mediable_type' => get_class($model),
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

    public function destroy(Media $media): array
    {
        DB::transaction(function () use ($media) {
            $this->deleteFile($media->path, $media->disk);
            $media->delete();
        });
        return ['data' => null, 'code' => 204];
    }
}
