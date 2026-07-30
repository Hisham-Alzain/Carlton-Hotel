<?php

namespace App\Models;

use App\Jobs\PurgeMediaFile;
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

    /**
     * A deleted row takes its file with it — unless another row still names the
     * same `disk` + `path`.
     *
     * This lives on the model rather than in `MediaService` because it is the
     * one rule that must hold for *every* way a row can disappear: the library
     * screen, a nested `{parent}/images/{media}` route, and — since
     * `PurgesMedia` — a parent being deleted or cascaded away. Before it moved
     * here, deleting content left both the rows and the files behind forever.
     *
     * `deleted`, not `deleting`: the row must be gone before the file is, so a
     * failed delete can never leave a row pointing at a file that is not there.
     */
    protected static function booted(): void
    {
        static::deleted(function (Media $media): void {
            $media->purgeFileIfUnreferenced();
        });
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Queue the unlink when nothing else points at this file.
     *
     * `attachExisting()` copies rows that share one `disk` + `path`, so deleting
     * a placement must not unlink a file three other entities are still
     * rendering. `whereKeyNot()` keeps the check correct whether this row has
     * already been removed or not. The job re-checks before unlinking, which is
     * what makes the deferral safe.
     */
    public function purgeFileIfUnreferenced(): void
    {
        if ($this->path === null) {
            return;
        }

        $shared = static::query()
            ->where('disk', $this->disk)
            ->where('path', $this->path)
            ->whereKeyNot($this->getKey())
            ->exists();

        if ($shared) {
            return;
        }

        PurgeMediaFile::dispatch($this->disk, $this->path)->afterCommit();
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
