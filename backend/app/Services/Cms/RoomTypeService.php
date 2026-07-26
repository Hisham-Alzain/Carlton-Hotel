<?php

namespace App\Services\Cms;

use App\Base\BaseService;
use App\Models\Amenity;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RoomTypeService extends BaseService
{
    protected string $model = RoomType::class;
    protected array $with = ['images', 'amenityList'];

    public function indexPublic(): array
    {
        $query = RoomType::query()
            ->with($this->with)
            ->where('is_active', true)
            ->orderBy('sort_order');
        return ['data' => $query->paginate($this->perPage), 'code' => 200];
    }

    public function store(array $data): array
    {
        [$amenities, $attributes] = $this->splitAmenities($data);

        $model = DB::transaction(function () use ($attributes, $amenities) {
            $roomType = RoomType::create($attributes);
            if ($amenities !== null) {
                $roomType->amenityList()->sync($this->pivotPayload($amenities));
            }
            return $roomType;
        });

        $model->loadMissing($this->with);
        return ['data' => $model, 'code' => 201];
    }

    public function update(Model $model, array $data): array
    {
        [$amenities, $attributes] = $this->splitAmenities($data);

        DB::transaction(function () use ($model, $attributes, $amenities) {
            $model->update($attributes);
            if ($amenities !== null) {
                $model->amenityList()->sync($this->pivotPayload($amenities));
            }
        });

        $model->refresh()->loadMissing($this->with);
        return ['data' => $model, 'code' => 200];
    }

    protected function query(): Builder
    {
        return RoomType::query()->with($this->with)->orderBy('sort_order');
    }

    /**
     * Pull the amenity payload out of the validated data so the remainder can be
     * mass-assigned. Returns null for the amenity half when the key is absent,
     * which keeps a partial PUT from wiping the existing pivot rows.
     *
     * @return array{0: ?array, 1: array}
     */
    private function splitAmenities(array $data): array
    {
        if (! array_key_exists('amenities', $data)) {
            return [null, $data];
        }

        $amenities = $data['amenities'] ?? [];
        unset($data['amenities']);

        return [$amenities, $data];
    }

    /**
     * Map the request's `[{uuid, is_highlight}]` list onto pivot attributes,
     * resolving UUIDs to ids in one query rather than per row.
     */
    private function pivotPayload(array $amenities): array
    {
        $uuids = array_column($amenities, 'uuid');
        $ids   = Amenity::whereIn('uuid', $uuids)->pluck('id', 'uuid');

        $payload = [];
        foreach (array_values($amenities) as $index => $amenity) {
            $id = $ids[$amenity['uuid']] ?? null;
            if ($id === null) {
                continue;
            }
            $payload[$id] = [
                'is_highlight' => (bool) ($amenity['is_highlight'] ?? false),
                'sort_order'   => $amenity['sort_order'] ?? $index,
            ];
        }

        return $payload;
    }
}
