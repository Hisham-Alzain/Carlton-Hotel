<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

/**
 * Place existing assets on a parent.
 *
 * `exists:media,uuid` means an unknown uuid is a 422 listing the offending
 * index, not a 404 that says nothing about which of five uuids was wrong.
 * `distinct` rejects the same uuid twice in one call — the service is idempotent
 * per parent, so a duplicate would silently collapse and the caller would never
 * learn its payload was wrong.
 *
 * `max:50` bounds the batch: every uuid becomes a row, and an unbounded array
 * would let one request write as many as the payload limit allows.
 */
class AttachMediaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'media_uuids'   => ['required', 'array', 'min:1', 'max:50'],
            'media_uuids.*' => ['required', 'string', 'distinct', 'exists:media,uuid'],
        ];
    }
}
