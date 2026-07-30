<?php

namespace App\Services\Cms;

use App\Exceptions\NotFoundException;
use App\Filters\MediaFilter;
use App\Models\Media;
use App\Traits\FileTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Uploads, the media library, and the attachment between the two.
 *
 * Deliberately not a `BaseService`: `destroy()` here takes the parent as well as
 * the media row (the nested route's ownership check, see below), which is not the
 * `BaseService::destroy(Model)` contract. The page-size clamp is mirrored rather
 * than inherited for the same reason.
 */
class MediaService
{
    use FileTrait;

    /** Where library uploads land — no parent, so no per-entity directory. */
    private const LIBRARY_DIR = 'cms/library';

    /** Page size when the client does not ask. Mirrors `BaseService`. */
    private const PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * The library list. Newest first — an asset picker opens on what was just
     * uploaded — with `id` as the tie-break, because `created_at` has one-second
     * resolution and a page of assets uploaded in one batch would otherwise come
     * back in an order the database chose.
     *
     * @param  array<string, mixed>  $params
     */
    public function index(array $params = [], ?int $perPage = null): array
    {
        $query = Media::query()->orderByDesc('created_at')->orderByDesc('id');

        (new MediaFilter($params))->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    /**
     * Upload into the library with no parent.
     *
     * Same file contract as `attach()` — one `image`, 5 MB, jpg/png/webp — so the
     * dashboard can point either endpoint at the same form. The row is a real
     * asset the moment it exists; attaching it later is a separate decision.
     *
     * @param  array<string, mixed>  $attributes  optional `alt_text`, `title`, `sort_order`
     */
    public function upload(UploadedFile $file, array $attributes = []): array
    {
        $path = $this->storeFile($file, self::LIBRARY_DIR);

        $media = DB::transaction(fn () => Media::create([
            'mediable_type' => null,
            'mediable_id'   => null,
            'disk'          => 'public',
            'path'          => $path,
            'file_name'     => $file->getClientOriginalName(),
            'alt_text'      => $attributes['alt_text'] ?? null,
            'title'         => $attributes['title'] ?? null,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'sort_order'    => (int) ($attributes['sort_order'] ?? 0),
        ]));

        return ['data' => $media, 'code' => 201];
    }

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
     * Place existing assets on a parent, so one upload can serve several entities.
     *
     * The row is **copied**, not moved: `media` carries one `mediable_type` /
     * `mediable_id` pair, so a shared asset needs one row per placement. Both
     * rows name the same `disk` + `path`, which is why deletion checks for other
     * referents before touching the file (see `Media::purgeFileIfUnreferenced()`,
     * which every delete path runs through). Each placement then
     * owns its `sort_order` independently, and the library entry survives.
     *
     * Idempotent per parent: a uuid whose file is already on this parent returns
     * that row instead of adding a second copy, so a double-submitted form
     * cannot put the same photograph on a page twice.
     *
     * The source may itself be attached elsewhere — copying a room type's hero
     * onto a promotion is the whole point, and requiring a library round-trip
     * first would only make the dashboard upload it twice.
     *
     * @param  list<string>  $uuids
     *
     * @throws NotFoundException when any uuid does not exist
     */
    public function attachExisting(Model $parent, array $uuids): array
    {
        $uuids   = array_values(array_unique($uuids));
        $sources = Media::whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $missing = array_values(array_diff($uuids, $sources->keys()->all()));

        if ($missing !== []) {
            throw new NotFoundException(__('custom.errors.not_found'), [
                'media'  => $missing,
                'parent' => class_basename($parent),
            ]);
        }

        $attached = DB::transaction(function () use ($parent, $uuids, $sources): Collection {
            $existing = Media::query()
                ->where('mediable_type', $parent->getMorphClass())
                ->where('mediable_id', $parent->getKey())
                ->get();

            $next = $existing->max('sort_order');
            $next = $next === null ? 0 : (int) $next + 1;

            $rows = new Collection();

            foreach ($uuids as $uuid) {
                /** @var Media $source */
                $source = $sources[$uuid];

                $already = $existing->first(
                    fn (Media $row): bool => $row->disk === $source->disk && $row->path === $source->path
                );

                if ($already !== null) {
                    $rows->push($already);

                    continue;
                }

                $rows->push(Media::create([
                    'mediable_type' => $parent->getMorphClass(),
                    'mediable_id'   => $parent->getKey(),
                    'disk'          => $source->disk,
                    'path'          => $source->path,
                    'file_name'     => $source->file_name,
                    'alt_text'      => $source->getTranslations('alt_text'),
                    'title'         => $source->title,
                    'mime_type'     => $source->mime_type,
                    'size'          => $source->size,
                    'sort_order'    => $next++,
                ]));
            }

            return $rows;
        });

        return ['data' => $attached, 'code' => 201];
    }

    /** Editor metadata only — never the file, the parent, or the stored path. */
    public function update(Media $media, array $data): array
    {
        DB::transaction(fn () => $media->update($data));

        return ['data' => $media->refresh(), 'code' => 200];
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

        DB::transaction(fn () => $media->delete());

        return ['data' => null, 'code' => 204];
    }

    /**
     * Delete a row from the library screen, whatever it is attached to.
     *
     * Unscoped on purpose — this is the library's own route, addressed by the
     * media uuid alone, and `cms.edit` already gates it. The parent-scoped
     * `destroy()` above stays as the only way to reach a row *through* an
     * entity's URL, so the cross-parent guard it enforces is untouched.
     */
    public function purge(Media $media): array
    {
        DB::transaction(fn () => $media->delete());

        return ['data' => null, 'code' => 204];
    }

    /** Clamp into `[1, MAX_PER_PAGE]`; mirrors `BaseService::resolvePerPage()`. */
    private function resolvePerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return self::PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
