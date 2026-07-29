<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Filters\SpaServiceFilter;
use App\Models\SpaService;

class SpaServiceService extends BaseService
{
    protected string $model = SpaService::class;
    protected ?string $filter = SpaServiceFilter::class;
}
