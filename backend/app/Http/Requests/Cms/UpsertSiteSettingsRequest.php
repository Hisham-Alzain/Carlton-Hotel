<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Enums\SettingType;
use App\Support\TranslatableRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpsertSiteSettingsRequest extends BaseRequest
{
    /**
     * A `text` slot is a headline or a one-line label, not an article. Generous
     * enough that no real caption trips it, small enough that the column is not
     * a dumping ground.
     */
    private const TEXT_MAX = 5000;

    /** `richtext` is body copy, so it gets the room a paragraph editor needs. */
    private const RICHTEXT_MAX = 65535;

    /** A URL or media path. Comfortably past the ~2000 char browser ceiling. */
    private const LINK_MAX = 2048;

    /**
     * An `url` value is "an absolute or root-relative link" (see SettingType).
     *
     * Not Laravel's `url` rule: that rejects `/careers`, and a root-relative
     * href is exactly what an internal footer link is. Not `active_url` either —
     * validation must not make a DNS call, and a link may legitimately point at
     * a host that is not resolvable from the API server.
     */
    private const LINK_PATTERN = '/^(?:https?:\/\/[^\s]+|mailto:[^\s]+|tel:[^\s]+|\/[^\s]*)$/i';

    public function rules(): array
    {
        $rules = [
            'settings'             => ['required', 'array', 'min:1'],
            'settings.*.group'     => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'settings.*.key'       => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'settings.*.type'      => ['required', Rule::in(SettingType::values())],
            'settings.*.is_active' => ['boolean'],
        ];

        $rows = $this->input('settings');

        if (! is_array($rows)) {
            // `settings` itself is the error; there are no rows to key rules to.
            // The wildcard form still has to exist so a scalar `settings` does
            // not silently skip the per-row presence check.
            return $rules + ['settings.*.value' => ['present']];
        }

        // `present`, not `nullable`: a caller that omits `value` entirely would
        // otherwise have it read as null and blank a stored setting by accident.
        // Clearing a setting must be an explicit `"value": null`. Keyed per row
        // rather than through `settings.*.value` so the shape rules below —
        // which differ per row, because `type` does — can be appended to the
        // same key without relying on wildcard/explicit merge order.
        foreach (array_keys($rows) as $index) {
            $rules["settings.{$index}.value"] = ['present'];
        }

        foreach ($rows as $index => $row) {
            $type = is_array($row) ? SettingType::tryFrom((string) ($row['type'] ?? '')) : null;

            if ($type === null) {
                // No type, or a type the enum does not know: `settings.*.type`
                // already reports that. Guessing a shape here would report the
                // same row twice with the second message contradicting the first.
                continue;
            }

            foreach ($this->valueRules($index, $type, is_array($row) ? ($row['value'] ?? null) : null) as $key => $list) {
                $rules[$key] = array_merge($rules[$key] ?? [], $list);
            }
        }

        return $rules;
    }

    /**
     * Rules for one row's `value`, derived from the `type` that row declares.
     *
     * `value` is a json column on purpose — a phone number, a locale map of
     * headlines and a locale map of address lines all live in it — so there is
     * no single column type to lean on. `type` is the only statement of intent
     * the row carries, and until now nothing checked the value against it: a
     * `url` slot could hold an array, a `bool` could hold `"banana"`, and both
     * reached the public site's footer, contact block and hero unexamined.
     *
     * Two shapes are legal for the copy types, matching what the module already
     * stores (see `CmsContentSeeder::siteSettings()`):
     *
     *   - a bare scalar — `contact.phone` is not translated, so it is one string;
     *   - a locale map — `footer.tagline` is `{en: …, ar: …, fr: …}`.
     *
     * The distinction is made from the submitted value rather than from `type`,
     * because both shapes are valid for the same type. An array is therefore
     * read as a locale map and its keys are restricted to `cms.locales`, which
     * is what makes `{0: "…", 1: "…"}` (a list where a map belongs) a 422 instead
     * of a footer that renders the string "0".
     *
     * `bool` and `json` are single-shape by nature: a feature flag is not
     * translated, and a `json` slot is the widget's structured editor — a bare
     * string there is the caller having picked the wrong type.
     *
     * `null` is accepted for every type. It is how a setting is cleared, and how
     * the four `social` slots ship: seeded inactive with no handle yet.
     *
     * @return array<string, list<string>>
     */
    private function valueRules(int|string $index, SettingType $type, mixed $value): array
    {
        $path = "settings.{$index}.value";

        if ($type === SettingType::BOOL) {
            // No locale-map branch: an array fails `boolean`, which is the
            // message a caller who sent `{en: true}` needs to read.
            return [$path => ['nullable', 'boolean']];
        }

        if ($type === SettingType::JSON) {
            return [$path => ['nullable', 'array']];
        }

        $scalar = match ($type) {
            SettingType::TEXT     => ['string', 'max:' . self::TEXT_MAX],
            SettingType::RICHTEXT => ['string', 'max:' . self::RICHTEXT_MAX],
            SettingType::IMAGE    => ['string', 'max:' . self::LINK_MAX],
            SettingType::URL      => ['string', 'max:' . self::LINK_MAX, 'regex:' . self::LINK_PATTERN],
            default               => ['string'],
        };

        if (! is_array($value)) {
            return [$path => ['nullable', ...$scalar]];
        }

        return [
            // `array:en,ar,…` is what rejects a list: its keys are 0 and 1, and
            // neither is a locale the CMS accepts.
            $path         => ['nullable', 'array:' . implode(',', TranslatableRules::locales())],
            "{$path}.*"   => ['nullable', ...$scalar],
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
