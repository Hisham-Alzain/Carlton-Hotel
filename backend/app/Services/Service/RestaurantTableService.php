<?php

namespace App\Services\Service;

use App\Base\BaseService;
use App\Models\DiningVenue;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Model;

class RestaurantTableService extends BaseService
{
    protected string $model = RestaurantTable::class;
    protected array $with = ['diningVenue'];

    public function store(array $data): array
    {
        $data = $this->resolveVenue($data);
        return parent::store($data);
    }

    public function update(Model $model, array $data): array
    {
        $data = $this->resolveVenue($data);
        return parent::update($model, $data);
    }

    private function resolveVenue(array $data): array
    {
        if (isset($data['dining_venue_uuid'])) {
            $data['dining_venue_id'] = DiningVenue::where('uuid', $data['dining_venue_uuid'])->value('id');
            unset($data['dining_venue_uuid']);
        }
        return $data;
    }
}
