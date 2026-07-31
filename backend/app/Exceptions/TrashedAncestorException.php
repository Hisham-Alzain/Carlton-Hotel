<?php

namespace App\Exceptions;

/**
 * A restore was asked for while the record above it is still in the bin.
 *
 * 409 rather than 422: nothing about the request is malformed and the caller
 * holds `cms.restore`. It conflicts with the current state of a *different*
 * resource, which is what `RoomAlreadyAssignedException` and
 * `NoAvailabilityException` already use 409 for here.
 *
 * `context` carries the way out, because "cannot restore" with no subject is a
 * dead end an editor answers by clicking again:
 *
 *     "error_code": "ancestor_trashed",
 *     "context": {
 *         "ancestor": { "type": "dining_venue", "uuid": "…" },
 *         "trashed_ancestors": [
 *             { "type": "dining_venue",  "uuid": "…" },
 *             { "type": "menu_category", "uuid": "…" }
 *         ]
 *     }
 *
 * `ancestor` is the one to restore first — the root-most binned one, whose own
 * restore cascades back down and usually clears the rest of the list in a single
 * act. `trashed_ancestors` is the full chain root-first, non-empty always, and
 * longer than one only where an ancestor was binned separately (and so was not
 * brought back by its parent's cascade, which restores only the children that
 * went down *with* it).
 *
 * `type` is the token `MediaResource` already publishes for `mediable_type`, so
 * a dashboard maps it to a module with the vocabulary it has. With `uuid` it is
 * enough to call that ancestor's own `POST /api/cms/{module}/{uuid}/restore`.
 */
class TrashedAncestorException extends DomainException
{
    public function errorCode(): string { return 'ancestor_trashed'; }
    public function statusCode(): int   { return 409; }
}
