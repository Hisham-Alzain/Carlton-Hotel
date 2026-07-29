<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Filters\RoomFilter;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoomService extends BaseService
{
    protected string $model = Room::class;
    protected ?string $filter = RoomFilter::class;
    protected array $with = ['roomType', 'images'];

    public function store(array $data): array
    {
        if (isset($data['room_type_uuid'])) {
            $data['room_type_id'] = RoomType::where('uuid', $data['room_type_uuid'])->value('id');
            unset($data['room_type_uuid']);
        }
        return parent::store($data);
    }

    public function update(Model $model, array $data): array
    {
        if (isset($data['room_type_uuid'])) {
            $data['room_type_id'] = RoomType::where('uuid', $data['room_type_uuid'])->value('id');
            unset($data['room_type_uuid']);
        }
        return parent::update($model, $data);
    }

    public function indexPublic(?int $perPage = null): array
    {
        $query = Room::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('room_type_id')
            ->orderBy('number');
        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    protected function query(): Builder
    {
        return Room::query()->with($this->with)->orderBy('number');
    }
}
