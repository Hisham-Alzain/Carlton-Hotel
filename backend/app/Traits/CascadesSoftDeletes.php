<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Carry a soft delete down the relations the DATABASE used to cascade.
 *
 * ## The conflict this exists to resolve
 *
 * The CMS content tables are wired with `ON DELETE CASCADE`
 * (`rooms.room_type_id`, `menu_categories.dining_venue_id`,
 * `menu_items.menu_category_id`, `gallery_items.gallery_category_id`). A hard
 * delete of a dining venue therefore removed its menu categories and every dish
 * under them, in the database, without Eloquent seeing a thing.
 *
 * `SoftDeletes` turns that hard delete into an `update … set deleted_at = ?`.
 * The referential action never fires, because no row is deleted. The venue
 * disappears from every index and show route while its menu categories and
 * dishes stay live — and the public site reads dishes through
 * `MenuItemService::menuForVenue()`, which joins `menu_categories` rather than
 * asking the venue. Result: a venue nobody can see, still serving a menu.
 *
 * Two ways out. Scope every child read by its parent's `deleted_at`, which means
 * auditing (and re-auditing, forever) every query, join, resource and filter
 * that can reach a child — including the two hand-written joins in
 * `GalleryItemService` and `MenuItemService` that bypass the relation entirely.
 * Or cascade the soft delete, so each child's own `SoftDeletes` global scope hides
 * it — one mechanism, applied where the parent already knows its children, and
 * automatically correct for queries nobody has written yet.
 *
 * This trait is the second. `softDeleteCascades()` names the relations, which are
 * exactly the `ON DELETE CASCADE` edges: the trait replaces a database
 * behaviour, so it copies its shape rather than inventing one.
 *
 * ## Events, and why each one
 *
 * - **`deleted`, soft path** — fires *after* `runSoftDelete()`, which is the
 *   first moment `$model->deleted_at` holds the timestamp the children need to
 *   be matched against on restore. `deleting` is too early: `deleted_at` is
 *   still null there.
 * - **`deleting`, force path** — fires *before* the row goes, so the children are
 *   still readable and can be force-deleted through Eloquent. That matters
 *   because `PurgesMedia` unlinks a row's images on `forceDeleted`: letting the
 *   database cascade take the children instead would fire no event for them and
 *   strand their photography forever. Once the children are gone through
 *   Eloquent, the database cascade has nothing left to do.
 * - **`restoring`** — fires while `deleted_at` is still readable, so the cascade
 *   can restore exactly the children that went down *with* the parent.
 *
 * ## Restore is not "un-delete everything"
 *
 * Children are restored only when their `deleted_at` is at or after the parent's.
 * A dish the editor deleted last week, before the venue went, was a separate
 * decision and stays deleted — restoring the venue must not resurrect it. In a
 * cascade the parent is stamped first and the children within the same statement
 * batch, so `>=` matches them and nothing earlier.
 */
trait CascadesSoftDeletes
{
    public static function bootCascadesSoftDeletes(): void
    {
        static::deleting(function (Model $model): void {
            if ($model->isForceDeleting()) {
                $model->cascadeForceDelete();
            }
        });

        static::deleted(function (Model $model): void {
            if (! $model->isForceDeleting()) {
                $model->cascadeSoftDelete();
            }
        });

        static::restoring(function (Model $model): void {
            $model->cascadeRestore();
        });
    }

    /**
     * Relations that follow this row into — and out of — the recycle bin. One
     * entry per `ON DELETE CASCADE` edge pointing at this table.
     *
     * Every named relation's model must itself use `SoftDeletes`; otherwise its
     * rows cannot be marked and the cascade would silently do nothing.
     *
     * @return list<string>
     */
    protected function softDeleteCascades(): array
    {
        return [];
    }

    /**
     * Soft-delete the children, recursing through their own hooks so a
     * two-level cascade (venue → categories → dishes) needs no special case.
     */
    public function cascadeSoftDelete(): void
    {
        $this->eachCascadeChild(
            fn ($relation) => $relation,
            fn (Model $child) => $child->delete(),
        );
    }

    /**
     * Permanently delete the children while they are still reachable.
     *
     * `withTrashed()`: the usual route to a force delete is "soft-delete, then
     * empty the bin", so the children are already marked by the time this runs
     * and the default scope would hide every one of them.
     */
    public function cascadeForceDelete(): void
    {
        $this->eachCascadeChild(
            fn ($relation) => $relation->withTrashed(),
            fn (Model $child) => $child->forceDelete(),
        );
    }

    /**
     * Restore the children this row took down with it, and only those.
     */
    public function cascadeRestore(): void
    {
        $deletedAt = $this->deleted_at;

        if ($deletedAt === null) {
            return;
        }

        $this->eachCascadeChild(
            fn ($relation) => $relation->onlyTrashed()->where('deleted_at', '>=', $deletedAt),
            fn (Model $child) => $child->restore(),
        );
    }

    /**
     * Walk each cascade relation row by row.
     *
     * Row at a time, not a mass `update`: a mass update fires no model events, and
     * the events are the whole mechanism — the next level of the cascade, and the
     * media purge, both hang off them.
     *
     * `reorder()` before `lazyById()` because two of these relations declare an
     * `orderBy('sort_order')`. `lazyById()` appends its own `order by id` and
     * pages with `where id > <last row's id>`; with a non-id ordering in front,
     * the last row of a page is not the largest id on it and the walk would skip
     * rows. Clearing the order first restores the forward-by-key invariant, and
     * the order is irrelevant to a delete.
     *
     * @param  callable(mixed): mixed          $scope
     * @param  callable(Model): void           $apply
     */
    private function eachCascadeChild(callable $scope, callable $apply): void
    {
        foreach ($this->softDeleteCascades() as $relation) {
            $scope($this->{$relation}())->reorder()->lazyById()->each($apply);
        }
    }
}
