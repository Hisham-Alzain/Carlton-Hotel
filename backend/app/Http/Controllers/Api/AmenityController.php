<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Http\Resources\Cms\AmenityResource;
use App\Services\Cms\AmenityService;

class AmenityController extends BasePublicIndexController
{
    protected ?string $resource = AmenityResource::class;

    public function __construct(private readonly AmenityService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }
}
