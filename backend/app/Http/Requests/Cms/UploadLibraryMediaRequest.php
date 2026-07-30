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
    /**
     * An empty `sort_order` means "not stated", not 422.
     *
     * Same reasoning as `UpdateMediaRequest::prepareForValidation()` — a cleared
     * number input submits `sort_order=`, `ConvertEmptyStringsToNull` turns that
     * into null, and `integer` without `nullable` rejects null. Dropping the key
     * lets `MediaService::upload()` fall back to its own default of 0, which is
     * what "the editor did not choose an order" should mean on a create.
     *
     * Restated here rather than shared: the two requests have no common ancestor
     * below `BaseRequest`, and `BaseRequest` is the wrong place for one field's
     * normalisation. The per-parent `UploadMediaRequest` this extends carries the
     * same gap on its own routes and is left untouched — see the report.
     */
    protected function prepareForValidation(): void
    {
        foreach ([$this->getInputSource(), $this->query] as $bag) {
            $values = $bag->all();

            if (array_key_exists('sort_order', $values)
                && ($values['sort_order'] === null || $values['sort_order'] === '')) {
                $bag->remove('sort_order');
            }
        }
    }

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
