<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Permanently destroying a content record takes its `media` rows with it — and,
 * through `Media`'s own delete hook, the stored files nothing else references.
 *
 * ## Why `forceDeleted` and not `deleting`
 *
 * This hook used to hang off `deleting`, which was correct while every CMS delete
 * was a hard delete. Now that the content models use `SoftDeletes`, `deleting`
 * fires on the *recoverable* delete too: the editor clicks delete, the row is
 * merely marked, and the photography would be unlinked from storage anyway. The
 * recycle bin would hand back a room type with every image gone — data loss
 * wearing a safety feature's clothes.
 *
 * `forceDeleted` fires only when the row genuinely leaves the table, which is
 * exactly when its images stop having a referent. A soft delete now keeps its
 * media rows untouched, and a restore comes back whole.
 *
 * Models that do *not* use `SoftDeletes` keep the original `deleting` hook: for
 * them a delete is already permanent and `forceDeleted` would never fire, so
 * switching unconditionally would have quietly stopped purging anything.
 *
 * ## Why a model event and not a service call
 *
 * Every cascade child here is wired `ON DELETE CASCADE` at the database level
 * (`rooms.room_type_id`, `menu_categories.dining_venue_id`,
 * `menu_items.menu_category_id`, `gallery_items.gallery_category_id`), so a hook
 * in each service's `destroy()` could not reach them — the database deletes them
 * itself and no Eloquent event fires. It would also have to be repeated in
 * thirteen services and would still miss every non-HTTP delete (a re-seed, an
 * artisan command, a `tinker` session).
 *
 * The descent itself is no longer this trait's job. `CascadesSoftDeletes`
 * force-deletes the children through Eloquent while they are still readable, so
 * each one fires its own `forceDeleted` and purges its own media. One list of
 * cascade edges (`softDeleteCascades()`), one walk, and the database's own
 * cascade is left with nothing to do. Before that existed this trait walked the
 * same edges itself under `mediaCascades()`; keeping both would have been two
 * mechanisms racing over the same rows.
 *
 * Layering holds: this is a model knowing about another model (`Media`) and a
 * trait, which is precisely what the layer table permits. Nothing here touches a
 * service, `request()`, or a response. The file-unlink decision is not duplicated
 * either — it lives in exactly one place, `Media`'s delete hook, so a shared
 * asset placed on several parents keeps its file until the last row goes.
 */
trait PurgesMedia
{
    public static function bootPurgesMedia(): void
    {
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::forceDeleted(function (Model $model): void {
                $model->purgeMedia();
            });

            return;
        }

        static::deleting(function (Model $model): void {
            $model->purgeMedia();
        });
    }

    /**
     * Drop this record's media rows.
     *
     * Rows are deleted one at a time, not with a mass `delete()` on the relation:
     * a mass delete fires no model events, and `Media`'s delete hook is what
     * unlinks the file. `lazyById()` keeps a room type with hundreds of images off
     * the heap, and is safe to delete through — it walks forward by primary key,
     * so rows it has already visited cannot shift the cursor.
     */
    public function purgeMedia(): void
    {
        // `getMorphClass()`, never `get_class()` — `Relation::morphMap()` is
        // process-wide, and an aliased model stores the alias in `mediable_type`.
        // Matches `MediaService`.
        Media::query()
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey())
            ->lazyById()
            ->each(fn (Media $row) => $row->delete());
    }
}
