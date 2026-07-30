<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Reads over `site_settings`.
 *
 * This module does not follow standard CRUD: no route reaches the inherited
 * `index/show/store/update/destroy`. The only write path is
 * `App\Actions\Cms\UpsertSiteSettingsAction`, because a settings form is one
 * atomic editorial act rather than N single-row edits. `BaseService` is still
 * the parent so the class keeps the repo's service contract (`$model`,
 * `resolvePerPage`, the `['data' => …, 'code' => …]` return shape) and so a
 * future single-setting endpoint has the CRUD verbs already in place.
 */
class SiteSettingService extends BaseService
{
    protected string $model = SiteSetting::class;

    /**
     * Every setting — drafts included — bucketed by group for the CMS screen.
     *
     * NOT PAGINATED, and that is not an oversight. This table holds one row per
     * copy slot on the website: a few dozen rows, bounded by design work rather
     * than by user activity. The CMS renders the whole form at once, so a page-2
     * of settings would be an editor silently losing half their form. The
     * "always paginate" rule exists to stop unbounded result sets; this set is
     * bounded, and the ceiling is a schema-level decision that a reviewer sees.
     *
     * @return array<string, Collection<int, SiteSetting>>
     */
    public function grouped(): array
    {
        return SiteSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->all();
    }

    /**
     * Flat `{group: {key: value}}` map of ACTIVE settings, for the public site.
     *
     * `is_active` is hardcoded here and the method takes no filter — same rule
     * as every other public read in the CMS: the website asks for the settings,
     * not for a query, so no query string can reach this builder and expose a
     * draft phone number.
     *
     * @return array<string, array<string, mixed>>
     */
    public function publicMap(): array
    {
        $map = [];

        $settings = SiteSetting::query()
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        foreach ($settings as $setting) {
            $map[$setting->group][$setting->key] = $setting->value;
        }

        return $map;
    }
}
