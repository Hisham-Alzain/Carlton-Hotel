<?php

namespace App\Base;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every rule this project can actually fail, mapped onto `custom.validation.*`.
     *
     * Laravel's own `validation.php` ships only in `lang/en`, and `.env` sets
     * `APP_FALLBACK_LOCALE=ar` — so any rule missing from this map resolved to
     * nothing in ar/fr/tr/es and the `errors` payload carried the raw key
     * (`"validation.numeric"`) where a sentence belongs. `message` came back
     * localized beside it, so one 422 spoke two languages.
     *
     * The list is the audited set of rules reachable from this project's
     * FormRequests, not Laravel's full rule table: a rule nobody uses needs no
     * translation, and an unused key is a key nobody keeps up to date. Adding a
     * rule to a FormRequest means adding it here and to `custom.validation` in
     * all five locales — `LocaleFoundationTest` enforces the second half.
     *
     * Placeholders are passed through as literals (`':max' => ':max'`) so the
     * validator's own replacers still fill them from the failing rule's
     * parameters.
     */
    public function messages(): array
    {
        return [
            'required'         => __('custom.validation.required',         ['attribute' => ':attribute']),
            'required_if'      => __('custom.validation.required_if',      ['attribute' => ':attribute', 'other' => ':other', 'value' => ':value']),
            'required_without' => __('custom.validation.required_without', ['attribute' => ':attribute', 'values' => ':values']),
            'present'          => __('custom.validation.present',          ['attribute' => ':attribute']),
            'string'           => __('custom.validation.string',           ['attribute' => ':attribute']),
            'boolean'          => __('custom.validation.boolean',          ['attribute' => ':attribute']),
            'integer'          => __('custom.validation.integer',          ['attribute' => ':attribute']),
            'numeric'          => __('custom.validation.numeric',          ['attribute' => ':attribute']),
            'array'            => __('custom.validation.array',            ['attribute' => ':attribute']),
            'email'            => __('custom.validation.email',            ['attribute' => ':attribute']),
            'date'             => __('custom.validation.date',             ['attribute' => ':attribute']),
            'date_format'      => __('custom.validation.date_format',      ['attribute' => ':attribute', 'format' => ':format']),
            'after'            => __('custom.validation.after',            ['attribute' => ':attribute', 'date' => ':date']),
            'after_or_equal'   => __('custom.validation.after_or_equal',   ['attribute' => ':attribute', 'date' => ':date']),
            'max'              => __('custom.validation.max',              ['attribute' => ':attribute', 'max' => ':max']),
            'min'              => __('custom.validation.min',              ['attribute' => ':attribute', 'min' => ':min']),
            'gte'              => __('custom.validation.gte',              ['attribute' => ':attribute', 'value' => ':value']),
            'size'             => __('custom.validation.size',             ['attribute' => ':attribute', 'size' => ':size']),
            'in'               => __('custom.validation.in',               ['attribute' => ':attribute']),
            'regex'            => __('custom.validation.regex',            ['attribute' => ':attribute']),
            'file'             => __('custom.validation.file',             ['attribute' => ':attribute']),
            'image'            => __('custom.validation.image',            ['attribute' => ':attribute']),
            'mimes'            => __('custom.validation.mimes',            ['attribute' => ':attribute', 'values' => ':values']),
            'unique'           => __('custom.validation.unique',           ['attribute' => ':attribute']),
            'exists'           => __('custom.validation.exists',           ['attribute' => ':attribute']),
            'distinct'         => __('custom.validation.distinct',          ['attribute' => ':attribute']),

            // `Rule::enum()` is a ValidationRule object, not a string rule: it
            // fails with its own `$fail('validation.enum')`, and Laravel looks a
            // custom message up by the rule's FQCN (see
            // Validator::validateUsingCustomRule → getFromLocalArray), never by
            // the short name `enum`. Seventeen requests use it, so without this
            // line every enum rejection in ar/fr/tr/es read "validation.enum".
            Enum::class        => __('custom.validation.enum',             ['attribute' => ':attribute']),
        ];
    }
}
