<?php

namespace App\Models;

use App\Traits\FileTrait;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasUuid, FileTrait, LogsActivity;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
        'sort_order',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return $this->fileUrl($this->path, $this->disk);
    }
}
