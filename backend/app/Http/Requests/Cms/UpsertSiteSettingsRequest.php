<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Enums\SettingType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpsertSiteSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'settings'             => ['required', 'array', 'min:1'],
            'settings.*.group'     => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'settings.*.key'       => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            // `present`, not `nullable`: a caller that omits `value` entirely
            // would otherwise have it read as null and blank a stored setting by
            // accident. Clearing a setting must be an explicit `"value": null`.
            // No type rule beyond that — the column is json precisely so a
            // string, a bool, a locale map and a nested list are all legal.
            'settings.*.value'     => ['present'],
            'settings.*.type'      => ['required', Rule::in(SettingType::values())],
            'settings.*.is_active' => ['boolean'],
        ];
    }

    /**
     * Reject a payload that names the same (group, key) twice.
     *
     * The database's composite unique cannot catch this: an upsert would simply
     * write the row and then overwrite it, so the caller silently gets whichever
     * of its two conflicting values happened to come last. Two entries for one
     * key means the client built the payload wrong, and it should hear about it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = $this->input('settings');

            if (! is_array($rows)) {
                return;
            }

            $seen = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row) || ! is_string($row['group'] ?? null) || ! is_string($row['key'] ?? null)) {
                    continue; // the per-field rules already reported this one
                }

                $identity = $row['group'] . '.' . $row['key'];

                if (isset($seen[$identity])) {
                    $validator->errors()->add(
                        "settings.{$index}.key",
                        __('custom.validation.distinct', ['attribute' => $identity]),
                    );
                    continue;
                }

                $seen[$identity] = true;
            }
        });
    }
}
