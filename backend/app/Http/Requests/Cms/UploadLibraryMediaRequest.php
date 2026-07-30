<?php

namespace App\Http\Requests\Cms;

use App\Support\TranslatableRules;

/**
 * A library upload: the same file contract as a per-parent upload, plus the
 * editor metadata the library screen collects at the same time.
 *
 * Extends `UploadMediaRequest` rather than restating its rules so the two
 * endpoints cannot drift on `max:5120` or the mime whitelist — and so the
 * per-parent contract stays byte-identical, without the optional metadata.
 */
class UploadLibraryMediaRequest extends UploadMediaRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Alt text is the a11y/SEO string, so it is translatable and optional
            // in every locale — an editor uploading in a hurry must not be blocked,
            // but the field has to exist for them to come back to.
            ...TranslatableRules::optional('alt_text', ['string', 'max:255']),
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
