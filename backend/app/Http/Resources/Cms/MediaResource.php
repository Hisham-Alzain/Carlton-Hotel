<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'url'        => $this->url,
            'file_name'  => $this->file_name,
            // Whole locale map, like every other translatable field: the website
            // switches language client-side off a single fetch, and `alt` has to
            // switch with it. `[]` when the editor has not written one yet.
            'alt_text'   => $this->getTranslations('alt_text'),
            'title'      => $this->title,
            'mime_type'  => $this->mime_type,
            'size'       => $this->size,
            'sort_order' => $this->sort_order,

            // Which record this placement sits on. `GET /cms/media?mediable_type=`
            // could already narrow the library to "assets on room types", but a
            // row said nothing about *which* room type, so a picker could show
            // the filter and not the answer. `null` for a library asset nobody
            // has placed.
            //
            // The token, never the stored string: `mediable_type` holds whatever
            // `getMorphClass()` returned when the row was written — an alias for
            // the models in `Relation::morphMap()`, `App\Models\X` for the rest —
            // and a client should not have to know which, nor be handed this
            // application's namespace to render a label. `MediaFilter` takes this
            // same token back (and still accepts the FQCN it always did).
            'mediable_type' => static::typeToken($this->mediable_type),

            // The parent's uuid rather than `mediable_id`: that column is the
            // sequential primary key, which nothing in this project puts on the
            // wire, and every media-bearing model is addressed by uuid on every
            // other CMS route. `whenLoaded` because a nested render (a room
            // type's own `images`) has no inverse relation loaded — the key is
            // simply absent there instead of costing a query per row.
            'mediable_uuid' => $this->whenLoaded('mediable', fn () => $this->mediable->uuid),

            // How many rows — placements plus the library entry — name the same
            // stored file. `1` means deleting this row unlinks the file; `3`
            // means two other records keep rendering it. Computed in one
            // correlated subquery by `MediaService::selectUsageCount()`, so this
            // is a plain column read; absent wherever the query did not ask for
            // it, which is every context but the library list.
            'usage_count'   => $this->when(
                $this->usage_count !== null,
                fn (): int => (int) $this->usage_count,
            ),
        ];
    }

    /**
     * The API-facing name for a stored `mediable_type`.
     *
     * A row written for an aliased model already holds a short token
     * (`spa_service`); one written for any other model holds the FQCN. Both
     * arrive here and leave as the same shape — `App\Models\GalleryItem` and a
     * hypothetical `gallery_item` alias both render `gallery_item` — so a client
     * keeps working the day a media-bearing model joins `Relation::morphMap()`,
     * which is the migration `MediaService::destroy()` already guards against.
     */
    public static function typeToken(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        // Not a class name, so it is already an alias someone chose to publish.
        return class_exists($stored) ? Str::snake(class_basename($stored)) : $stored;
    }
}
