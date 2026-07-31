<?php

namespace App\Support;

use App\Traits\CascadesSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * What the recycle bin knows about its own shape.
 *
 * Two questions are asked of it, and both have the same answer underneath:
 *
 * 1. **Restore** — "is anything above this row still in the bin?" A room whose
 *    room type is trashed must not come back alone: it would be live, bookable
 *    and reachable under a parent that appears in no index, no show route and no
 *    public page. `BaseService::restore()` asks here and refuses.
 * 2. **Retention** — "which models hold binned rows, and in what order should a
 *    purge walk them?" `cms:purge-bin` asks here.
 *
 * ## Why the edges are derived, not listed
 *
 * The parent → child edges already exist, once, as `softDeleteCascades()` on the
 * four models that own one (`RoomType → rooms`, `DiningVenue → menuCategories`,
 * `MenuCategory → items`, `GalleryCategory → items`). Writing them out a second
 * time here — inverted, as child → parent — would be two lists to keep in step,
 * and the failure mode of them drifting is silent: a restore that stops
 * refusing, or refuses naming the wrong record. So this class reads
 * `CascadesSoftDeletes::cascadeRelations()` and inverts it, taking the foreign
 * key and owner key from the relation object itself rather than guessing at
 * `{table}_id`.
 *
 * A relation gives everything needed to walk *up* it without the child having to
 * declare a `belongsTo`: `getForeignKeyName()` is the column on the child,
 * `getLocalKeyName()` the column on the parent. `MenuItem::category()` and
 * `Room::roomType()` do both exist, but relying on them would mean a second
 * convention ("every cascade child must name its inverse") that nothing enforces.
 *
 * ## Discovery
 *
 * `app/Models/*.php` is scanned once per process and memoised. The alternative —
 * a registry array — is the hardcoded second list this class exists to avoid.
 * The scan autoloads the model classes, which a request touching the recycle bin
 * would largely do anyway, and happens at most once.
 */
final class RecycleBin
{
    /**
     * Child class → the edge that reaches its parent.
     *
     * One entry per child: the cascade is a forest of `hasMany` edges and no
     * table in it hangs off two parents. If one ever did, the later model file
     * alphabetically would win and the ancestry walk would follow only that
     * branch — which is why `modelsWithBin()` is pinned by a test that lists the
     * soft-deletable models by name, so a new shape has to be looked at.
     *
     * @var array<class-string<Model>, array{parent: class-string<Model>, foreign_key: string, owner_key: string}>|null
     */
    private static ?array $edges = null;

    /** @var list<class-string<Model>>|null */
    private static ?array $models = null;

    /**
     * Ancestors of `$model` that are themselves in the bin, **root-most first**.
     *
     * Root-most first because that is restore order. `cascadeRestore()` brings
     * back the children that went down *with* a row (`deleted_at >= ` the
     * parent's), so restoring the outermost trashed ancestor usually resurrects
     * everything below it in one act and the rest of this list evaporates. When
     * it does not — a category binned last week, then its venue binned today —
     * the remaining entries are the ones that were separate decisions and still
     * need separate restores.
     *
     * Empty means the row is free to come back.
     *
     * @return list<Model>
     */
    public static function trashedAncestors(Model $model): array
    {
        $edges   = self::edges();
        $chain   = [];
        $seen    = [];
        $current = $model;

        while (($edge = $edges[$current::class] ?? null) !== null) {
            $foreign = $current->getAttribute($edge['foreign_key']);

            if ($foreign === null) {
                break;
            }

            $ancestor = self::query($edge['parent'])
                ->where($edge['owner_key'], $foreign)
                ->first();

            if ($ancestor === null) {
                break;
            }

            // A malformed edge set could close a loop; walking it would hang the
            // request rather than fail it.
            $fingerprint = $ancestor::class.':'.$ancestor->getKey();

            if (isset($seen[$fingerprint])) {
                break;
            }

            $seen[$fingerprint] = true;

            if (method_exists($ancestor, 'trashed') && $ancestor->trashed()) {
                $chain[] = $ancestor;
            }

            $current = $ancestor;
        }

        return array_reverse($chain);
    }

    /**
     * The API-facing name for a model — `room_type`, `dining_venue`.
     *
     * Same shape `MediaResource::typeToken()` publishes for `mediable_type`, so a
     * client reading `context.ancestor.type` off a refused restore is reading the
     * vocabulary it already has. Built from `getMorphClass()` so a model that
     * later joins `Relation::morphMap()` keeps handing out its published alias
     * rather than switching to a namespaced class name.
     */
    public static function typeToken(Model $model): string
    {
        $stored = $model->getMorphClass();

        return class_exists($stored) ? Str::snake(class_basename($stored)) : $stored;
    }

    /**
     * Every model that can hold a binned row, **deepest cascade level first**.
     *
     * Leaves before roots so the retention purge counts honestly. A dish and the
     * venue above it are stamped with the same `deleted_at` by the cascade, so
     * both fall out of the same retention window; force-deleting the venue first
     * would take the dish with it through `cascadeForceDelete()` and the dish's
     * own pass would then find nothing, reporting fewer rows than it actually
     * destroyed — and reporting a different number than the dry run did. Walking
     * upward instead means every eligible row is force-deleted by the pass that
     * counted it, dry run and real run agree, and each row fires its own
     * `forceDeleted` for `PurgesMedia`.
     *
     * @return list<class-string<Model>>
     */
    public static function modelsWithBin(): array
    {
        if (self::$models !== null) {
            return self::$models;
        }

        $models = array_values(array_filter(
            self::modelClasses(),
            static fn (string $class): bool => self::usesTrait($class, SoftDeletes::class),
        ));

        usort(
            $models,
            static fn (string $a, string $b): int => [self::depth($b), $a] <=> [self::depth($a), $b],
        );

        return self::$models = $models;
    }

    /**
     * How far below a cascade root this model sits. `RoomType` is 0, `Room` 1,
     * `MenuItem` 2.
     */
    public static function depth(string $class): int
    {
        $edges = self::edges();
        $depth = 0;

        while (($edge = $edges[$class] ?? null) !== null && $depth < 32) {
            $depth++;
            $class = $edge['parent'];
        }

        return $depth;
    }

    /**
     * Drop the memoised maps. Only tests need this — a process that has already
     * scanned `app/Models` cannot grow a new one.
     */
    public static function flush(): void
    {
        self::$edges  = null;
        self::$models = null;
    }

    /**
     * @return array<class-string<Model>, array{parent: class-string<Model>, foreign_key: string, owner_key: string}>
     */
    private static function edges(): array
    {
        if (self::$edges !== null) {
            return self::$edges;
        }

        $edges = [];

        foreach (self::modelClasses() as $class) {
            if (! self::usesTrait($class, CascadesSoftDeletes::class)) {
                continue;
            }

            $parent = new $class;

            foreach ($parent->cascadeRelations() as $name) {
                $relation = $parent->{$name}();

                // `hasMany`/`hasOne` only: those are the `ON DELETE CASCADE`
                // edges the trait mirrors, and they are the ones whose foreign
                // key sits on the child where the walk upward needs it.
                if (! $relation instanceof HasOneOrMany) {
                    continue;
                }

                $edges[$relation->getRelated()::class] = [
                    'parent'      => $class,
                    'foreign_key' => $relation->getForeignKeyName(),
                    'owner_key'   => $relation->getLocalKeyName(),
                ];
            }
        }

        return self::$edges = $edges;
    }

    /**
     * @return list<class-string<Model>>
     */
    private static function modelClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    private static function usesTrait(string $class, string $trait): bool
    {
        return in_array($trait, class_uses_recursive($class), true);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function query(string $class): \Illuminate\Database\Eloquent\Builder
    {
        $query = $class::query();

        // The ancestor being *in* the bin is the whole question, so the walk has
        // to see through the soft-delete scope. Guarded rather than assumed: a
        // cascade parent that does not soft-delete is a wiring mistake the trait
        // already documents, and this should not fatal on it.
        return self::usesTrait($class, SoftDeletes::class) ? $query->withTrashed() : $query;
    }
}
