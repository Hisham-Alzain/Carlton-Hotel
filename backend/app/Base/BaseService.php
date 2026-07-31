<?php

namespace App\Base;

use App\Exceptions\TrashedAncestorException;
use App\Support\RecycleBin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use LogicException;

abstract class BaseService
{
    protected string $model;

    /** Filter class instantiated from the query params handed in by the controller. */
    protected ?string $filter = null;

    protected array $with = [];

    /** Page size used when the client does not ask for one. */
    protected int $perPage = 15;

    /**
     * Ceiling on a client-supplied `per_page`. 100 covers the public website's
     * "give me the whole collection" request (`per_page: 100` in the site's
     * contentService) while keeping any single page — and its eager loads —
     * bounded. Larger values are clamped, not rejected.
     */
    protected int $maxPerPage = 100;

    /**
     * @param  array<string, mixed>  $params   Query-string params from the controller.
     * @param  BaseFilter|null       $filter   Explicit filter, overriding `$this->filter`.
     * @param  int|null              $perPage  Client-requested page size, or null for the default.
     */
    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $query = $this->query();

        $filter ??= $this->makeFilter($params);
        $filter?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    public function show(Model $model): array
    {
        $model->loadMissing($this->with);
        return ['data' => $model, 'code' => 200];
    }

    public function store(array $data): array
    {
        $model = DB::transaction(fn () => ($this->model)::create($data));
        $model->loadMissing($this->with);
        return ['data' => $model, 'code' => 201];
    }

    public function update(Model $model, array $data): array
    {
        DB::transaction(fn () => $model->update($data));
        $model->refresh()->loadMissing($this->with);
        return ['data' => $model, 'code' => 200];
    }

    /**
     * On a model using `SoftDeletes` this marks the row rather than removing it,
     * and `CascadesSoftDeletes` carries the mark down the relations the database
     * used to cascade. The wire contract is unchanged either way — 204, and the
     * record is gone from every index and show route.
     */
    public function destroy(Model $model): array
    {
        DB::transaction(fn () => $model->delete());
        return ['data' => null, 'code' => 204];
    }

    /**
     * The recycle bin: rows `destroy()` marked, most recently deleted first.
     *
     * Ordered by `deleted_at` rather than by the collection's own `sort_order`
     * (which several `query()` overrides impose) because a bin is read
     * chronologically — "what did I just delete" — not editorially.
     *
     * @param  array<string, mixed>  $params
     */
    public function trashed(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $this->assertSoftDeletable($this->model);

        $query = $this->query()->onlyTrashed();

        $filter ??= $this->makeFilter($params);
        $filter?->apply($query);

        $query->reorder('deleted_at', 'desc');

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    /**
     * Bring a marked row — and the children that went down with it — back.
     *
     * The cascade back up is `CascadesSoftDeletes::cascadeRestore()`, fired from
     * the model's `restoring` event, so this stays a one-liner and a restore
     * triggered from a console command behaves identically.
     */
    public function restore(Model $model): array
    {
        $this->assertSoftDeletable($model);
        $this->assertNoTrashedAncestor($model);

        DB::transaction(fn () => $model->restore());
        $model->refresh()->loadMissing($this->with);

        return ['data' => $model, 'code' => 200];
    }

    /**
     * Empty this row out of the bin for good.
     *
     * This is the point where the media purge finally runs (`PurgesMedia` hooks
     * `forceDeleted`) and where the descendants are removed through Eloquent so
     * their own media goes with them.
     */
    public function forceDestroy(Model $model): array
    {
        $this->assertSoftDeletable($model);

        DB::transaction(fn () => $model->forceDelete());

        return ['data' => null, 'code' => 204];
    }

    /**
     * Refuse a restore that would leave a live row under a binned one.
     *
     * The cascade takes children down with their parent; nothing took them back
     * up on their own. `POST /cms/rooms/{uuid}/restore` on a room whose room type
     * is still in the bin used to answer 200 and produce exactly that: a room
     * live and bookable under a type that appears in no index, no show route and
     * no public page — the orphan the cascade exists to prevent, created by the
     * verb that undoes it.
     *
     * Checked to the root, not one level. A dish under a live category under a
     * binned venue is still an orphan, and the category being live is precisely
     * what makes it look restorable.
     *
     * ## Why there is no `?with_ancestors=true`
     *
     * It was considered and rejected. Restoring an ancestor is not a quiet
     * side-effect: `cascadeRestore()` brings back *every* child that went down
     * with it, so `with_ancestors` on one dish would resurrect the venue, all its
     * categories and every other dish on the menu — a blast radius the caller
     * asked for one row of, hidden behind a query parameter, under a permission
     * check made against the dish. The `context` payload already names the
     * ancestor and its uuid, which is all a dashboard needs to offer "restore the
     * venue too" as a second, visible call to that venue's own restore endpoint —
     * same effect, but the actor sees what they are undoing and the audit trail
     * records it against the record actually restored.
     */
    protected function assertNoTrashedAncestor(Model $model): void
    {
        $trashed = RecycleBin::trashedAncestors($model);

        if ($trashed === []) {
            return;
        }

        $chain = array_map(static fn (Model $ancestor): array => [
            'type' => RecycleBin::typeToken($ancestor),
            'uuid' => $ancestor->getAttribute('uuid'),
        ], $trashed);

        throw new TrashedAncestorException(
            __('custom.errors.ancestor_trashed'),
            ['ancestor' => $chain[0], 'trashed_ancestors' => $chain],
        );
    }

    /**
     * Restoring what was never recoverable is a wiring mistake, not a rule a user
     * broke — hence `LogicException` and not a domain exception with an
     * `error_code`. It would mean a controller wired a bin endpoint to a service
     * whose model has no `deleted_at`, which is a bug to fix, not a 4xx to render.
     *
     * @param  Model|class-string<Model>  $model
     */
    protected function assertSoftDeletable(Model|string $model): void
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return;
        }

        $name = is_string($model) ? $model : $model::class;

        throw new LogicException("[{$name}] does not use SoftDeletes: it has no recoverable delete.");
    }

    protected function query(): Builder
    {
        return ($this->model)::query()->with($this->with);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function makeFilter(array $params): ?BaseFilter
    {
        return $this->filter === null ? null : new ($this->filter)($params);
    }

    /**
     * Clamp the client's page size into `[1, $maxPerPage]`, falling back to the
     * service default when it is absent, non-numeric or non-positive.
     */
    protected function resolvePerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return $this->perPage;
        }

        return min($perPage, $this->maxPerPage);
    }
}
