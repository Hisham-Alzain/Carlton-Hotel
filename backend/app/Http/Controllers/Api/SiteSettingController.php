<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Services\Cms\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends BaseController
{
    public function __construct(private readonly SiteSettingService $service) {}

    /**
     * Global site copy as a flat, grouped map of the ACTIVE settings:
     *
     *     {
     *       "success": true,
     *       "message": "…",
     *       "data": {
     *         "contact": {
     *           "address":       {"en": "Kafr Sousa, Damascus, …", "ar": "…", "fr": "…"},
     *           "address_lines": {"en": ["Kafr Sousa", "Damascus", "…"], "ar": […], "fr": […]},
     *           "email":         "reservations@carltonsyria.com",
     *           "hours_note":    {"en": "Available 24 hours a day", "ar": "…", "fr": "…"},
     *           "phone":         "+963 (0)11 000 00 00"
     *         },
     *         "footer":  {"copyright": {…}, "newsletter_heading": {…}, "tagline": {…}},
     *         "hero":    {"cta_label": {…}, "eyebrow": {…}, "heading": {…}, "subheading": {…}},
     *         "booking": {"availability_note": {…}, "cta_label": {…}},
     *         "seo":     {"meta_description": {…}, "site_title": {…}}
     *       },
     *       "request_id": "…"
     *     }
     *
     * DELIBERATE EXCEPTION TO THE PAGINATION CONVENTION. This response is NOT
     * paginated and NOT wrapped in `{items, meta}`, unlike every other public
     * index in this API. Two reasons, both load-bearing:
     *
     * 1. The consumer cannot read the paginated shape. The website funnels list
     *    responses through `normalizeList()` (`src/app/api/envelope.ts`), which
     *    looks for `data.items` as an array and otherwise returns
     *    `{items: [], …}`. A `{group: {key: value}}` map has no `items`, so
     *    routing it through that helper yields an empty list, not an error — the
     *    footer would render blank with nothing in the console. Handing it a map
     *    keeps the map out of that code path entirely.
     * 2. Half a settings map is not useful. Page 2 of the site configuration is
     *    a footer with no copyright line. The set is bounded by design work
     *    (one row per copy slot), so there is nothing for pagination to protect.
     *
     * `per_page` is therefore not accepted here either — there is no page.
     * Inactive settings are excluded; see `SiteSettingService::publicMap()`.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->service->publicMap(), 'custom.messages.success', 200, $request);
    }
}
