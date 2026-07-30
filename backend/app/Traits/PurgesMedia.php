<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;

/**
 * Deleting a content record takes its `media` rows with it — and, through
 * `Media`'s own delete hook, the stored files nothing else references.
 *
 * ## Why a model event and not a service call
 *
 * Every child table here is wired `ON DELETE CASCADE` at the database level
 * (`rooms.room_type_id`, `menu_categories.dining_venue_id`,
 * `menu_items.menu_category_id`, `gallery_items.gallery_category_id`). The
 * database deletes those rows itself, so no Eloquent event fires for them and
 * `RoomService::destroy()` is never called when a room type goes. A hook in
 * each service's `destroy()` therefore *cannot* reach the cascade paths, which
 * is exactly where orphans pile up fastest — one room type can take fifty
 * rooms' photography with it. It would also have to be repeated in thirteen
 * services and would still miss every non-HTTP delete (a re-seed, an artisan
 * command, a `tinker` session).
 *
 * A `deleting` event on the parent runs before the database cascade, which is
 * the only moment the descendant rows are still readable.
 *
 * Layering holds: this is a model knowing about another model (`Media`) and a
 * trait, which is precisely what the layer table permits. Nothing here touches
 * a service, `request()`, or a response. The file-unlink decision is not
 * duplicated either — it lives in exactly one place, `Media`'s delete hook, so
 * a shared asset placed on several parents keeps its file until the last row
 * goes.
 *
 * An observer would be the same code in a file that has to be registered; a
 * trait travels with the `images()` relation it belongs to, so a model that
 * gains media tomorrow gains cleanup in the same edit.
 */
trait PurgesMedia
{
    public static function bootPurgesMedia(): void
    {
        static::deleting(function (Model $model): void {
            $model->purgeMedia();
        });
    }

    /**
     * Relations whose rows the DATABASE deletes along with this one, and which
     * carry media of their own (directly or further down). Walked depth-first
     * before this row goes, because after that they are unreachable.
     *
     * Overridden by the models that head a cascade; empty for the leaves.
     *
     * @return list<string>
     */
    protected function mediaCascades(): array
    {
        return [];
    }

    /**
     * Drop this record's media rows, and its cascade descendants' first.
     *
     * Rows are deleted one at a time, not with a mass `delete()` on the
     * relation: a mass delete fires no model events, and `Media`'s delete hook
     * is what unlinks the file. `lazyById()` keeps a room type with hundreds of
     * images off the heap, and is safe to delete through — it walks forward by
     * primary key, so rows it has already visited cannot shift the cursor.
     */
    public function purgeMedia(): void
    {
        foreach ($this->mediaCascades() as $relation) {
            $this->{$relation}()->lazyById()->each(function (Model $child): void {
                if (method_exists($child, 'purgeMedia')) {
                    $child->purgeMedia();
                }
            });
        }

        // `getMorphClass()`, never `get_class()` — `Relation::morphMap()` is
        // process-wide, and an aliased model stores the alias in
        // `mediable_type`. Matches `MediaService`.
        Media::query()
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey())
            ->lazyById()
            ->each(fn (Media $row) => $row->delete());
    }
}
