<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

/**
 * Editor metadata on an existing asset. No `image`, no `path`, no `mediable_*`:
 * replacing a file is a new upload, and moving an asset between parents is
 * attach + delete, both of which leave an audit trail this route would not.
 */
class UpdateMediaRequest extends BaseRequest
{
    /**
     * An empty `sort_order` means "leave it alone", not 422.
     *
     * A number input that has been cleared submits `sort_order=`, which
     * `ConvertEmptyStringsToNull` hands the validator as null — and `integer`
     * without `nullable` fails on null, so the ordinary act of clearing a field
     * returned a validation error naming a field the editor had not filled in.
     * `nullable` alone would only move the problem: the column is
     * `unsignedSmallInteger NOT NULL DEFAULT 0`, so a validated null would reach
     * `update()` and fail in the database instead.
     *
     * Dropping the key is what makes it mean *unset* — `validated()` then omits
     * it and the stored value stands. This is the same rule the filter layer
     * applies to a blank query param (`BaseFilter` rule 2: empty means no
     * filter), and it is normalisation of the incoming payload, not a rule, so
     * it belongs here rather than in the service.
     */
    protected function prepareForValidation(): void
    {
        // Removed from the bags themselves rather than by replacing the whole
        // input with `all()`: `all()` folds uploaded files in, and writing those
        // back into the parameter bag is a change this normalisation has no
        // business making.
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
            ...TranslatableRules::optional('alt_text', ['string', 'max:255']),
            'title'      => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
