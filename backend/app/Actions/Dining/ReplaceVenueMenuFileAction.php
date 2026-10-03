<?php

namespace App\Actions\Dining;

use App\Models\DiningVenue;
use App\Models\Media;
use App\Services\Cms\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Sets a venue's menu file, replacing any previous one (Phase 8, D-27).
 *
 * One transaction: store the new `collection = menu` row, then delete every
 * older menu row of the venue. `Media::deleted` queues each old file's purge
 * after commit, unless another row still names it. The new file is written to
 * disk inside `MediaService::attach()` before its row; a failure after that
 * leaves an orphan file, exactly as `attach()` does today.
 */
class ReplaceVenueMenuFileAction
{
    public function __construct(private readonly MediaService $media) {}

    public function handle(DiningVenue $venue, UploadedFile $file, ?string $title): array
    {
        $new = DB::transaction(function () use ($venue, $file, $title): Media {
            // Serialize replacement and removal even when no menu row exists yet.
            DiningVenue::whereKey($venue->getKey())->lockForUpdate()->firstOrFail();

            $new = $this->media->attach($venue, $file, 0, 'menu', $title)['data'];

            Media::query()
                ->where('mediable_type', $venue->getMorphClass())
                ->where('mediable_id', $venue->getKey())
                ->where('collection', 'menu')
                ->whereKeyNot($new->getKey())
                ->get()
                ->each->delete();

            return $new;
        });

        return ['data' => $new, 'code' => 201];
    }
}
