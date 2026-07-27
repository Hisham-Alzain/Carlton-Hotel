<?php

namespace App\Services\Service;

use App\Models\ServiceCategory;

class ServiceCatalogService
{
    /**
     * The whole guest service menu in one un-paginated payload — it is bounded
     * at eight categories and roughly fifteen items, and the home screen needs
     * all of it at once.
     */
    public function index(): array
    {
        $data = ServiceCategory::query()
            ->with([
                'visibleItems',
                'defaultItem',
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ['data' => $data, 'code' => 200];
    }
}
