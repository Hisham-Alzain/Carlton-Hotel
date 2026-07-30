<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Unlink a stored file that no `media` row points at any more.
 *
 * Queued rather than inline because one delete can be hundreds of unlinks:
 * deleting a room type cascades to its rooms, a dining venue to its menu
 * categories and every dish in them, and each of those carried photography. On
 * an object-store disk that is one network round-trip per file, in an HTTP
 * request the editor is waiting on — exactly the work the performance rules say
 * belongs on the queue.
 *
 * Carries `disk` + `path`, never a `Media` id: by the time this runs the row is
 * gone on purpose, so a `SerializesModels` job would fail to resolve it.
 *
 * Re-checks for referents before unlinking, and is dispatched `afterCommit()`.
 * Together those make the job safe in the two ways a deferred unlink can go
 * wrong: a transaction that rolls back after the row was deleted never reaches
 * dispatch, and a placement created between dispatch and execution (the media
 * library copies rows that share one `disk` + `path`) is seen here and the file
 * is left alone. Idempotent, so a retry cannot delete a file that has since
 * been re-referenced.
 */
class PurgeMediaFile implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {
    }

    public function handle(): void
    {
        $stillReferenced = Media::query()
            ->where('disk', $this->disk)
            ->where('path', $this->path)
            ->exists();

        if ($stillReferenced) {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }
}
