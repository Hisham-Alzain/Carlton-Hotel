<?php

namespace App\Models;

use App\Traits\FileTrait;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An uploaded asset, optionally attached to a CMS entity.
 *
 * `mediable_type`/`mediable_id` are nullable: a row with neither is a library
 * asset nobody has placed yet. Attaching copies the row rather than moving it
 * (see `MediaService::attachExisting`), so one stored file can serve several
 * entities while each parent keeps its own `sort_order`, `alt_text` and `title`.
 * Every `morphMany(Media::class, 'mediable')` relation filters on the morph
 * columns with `=`, so library rows never leak into a parent's `images`.
 */
class Media extends Model
{
    use HasFactory, HasUuid, HasTranslations, FileTrait, LogsActivity;

    protected $table = 'media';

    /** Alt text is prose a screen reader speaks — it needs every CMS locale. */
    protected $translatable = ['alt_text'];

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'disk',
        'path',
        'file_name',
        'alt_text',
        'title',
        'mime_type',
        'size',
        'sort_order',
    ];

    protected $casts = [
        'size'       => 'integer',
        'sort_order' => 'integer',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Library assets: uploaded, not yet placed on any entity. */
    public function scopeUnattached(Builder $query): Builder
    {
        return $query->whereNull('mediable_type');
    }

    public function scopeAttached(Builder $query): Builder
    {
        return $query->whereNotNull('mediable_type');
    }

    public function getUrlAttribute(): string
    {
        return $this->fileUrl($this->path, $this->disk);
    }
}
