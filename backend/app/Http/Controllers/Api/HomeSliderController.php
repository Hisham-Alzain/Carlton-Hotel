<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Http\Resources\Cms\HomeSliderResource;
use App\Services\Cms\HomeSliderService;

class HomeSliderController extends BasePublicIndexController
{
    protected ?string $resource = HomeSliderResource::class;

    public function __construct(private readonly HomeSliderService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }
}
