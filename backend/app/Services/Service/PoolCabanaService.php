<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Filters\PoolCabanaFilter;
use App\Models\PoolCabana;

class PoolCabanaService extends BaseService
{
    protected string $model = PoolCabana::class;
    protected ?string $filter = PoolCabanaFilter::class;
}
