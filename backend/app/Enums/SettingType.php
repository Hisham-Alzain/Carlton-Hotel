<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Which editor widget the CMS renders for a `site_settings` row.
 *
 * This is a presentation hint, not a storage format — every value lands in the
 * same `json` column. It exists so the dashboard does not have to guess from
 * the shape of `value` whether an editor should see a one-line input, a rich
 * text area, an image picker or a toggle. Validated at the request boundary so
 * an unknown widget name is a 422 rather than a blank field in the CMS.
 */
enum SettingType: string
{
    use HasValues;

    /** Single-line copy. `value` is a string or a locale map of strings. */
    case TEXT = 'text';

    /** Multi-paragraph HTML copy. Same storage as TEXT, different editor. */
    case RICHTEXT = 'richtext';

    /** A media URL or path. The picker writes the resolved URL. */
    case IMAGE = 'image';

    /** An absolute or root-relative link. */
    case URL = 'url';

    /** A structured value the widget renders as a list or key/value editor. */
    case JSON = 'json';

    /** A boolean feature flag; the widget is a toggle. */
    case BOOL = 'bool';
}
