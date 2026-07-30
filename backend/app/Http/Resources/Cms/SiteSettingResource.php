<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * The CMS view of one setting. The public site never sees this shape — it gets
 * the flat `{group: {key: value}}` map from `Api\SiteSettingController::index()`.
 */
class SiteSettingResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'      => $this->uuid,
            'group'     => $this->group,
            'key'       => $this->key,
            // Passed through as decoded: a locale map stays a map, a scalar
            // stays a scalar. There is no `getTranslations()` call because the
            // model is not `HasTranslations` — see SiteSetting's docblock.
            'value'     => $this->value,
            // The widget hint, as its string value — the dashboard branches on
            // this, so it must be the enum's backing value, not its case name.
            'type'      => $this->type?->value,
            'is_active' => $this->is_active,
        ];
    }
}
