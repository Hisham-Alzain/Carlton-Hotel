<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The recycle-bin half of a CRUD controller: `trashed` / `restore` /
 * `forceDestroy`.
 *
 * Opt-in rather than part of `BaseCRUDController`, because only 17 of the 23
 * CMS resources have a bin. Inheriting the three verbs everywhere would put
 * unroutable public methods on the six controllers whose models are not
 * soft-deletable, where `BaseService::assertSoftDeletable()` would throw a
 * `LogicException` if anything ever reached them. `use HandlesRecycleBin;` is
 * also greppable: it says which controllers have a bin without reading routes.
 *
 * `trashed()` is inherited whole — no route-bound model, no FormRequest.
 * `restore` and `forceDestroy` address a *deleted* record, so their routes carry
 * `->withTrashed()`; that keeps working precisely because the child still
 * declares the concrete model type-hint that implicit binding reads.
 *
 * @phpstan-require-extends BaseIndexController
 */
trait HandlesRecycleBin
{
    public function trashed(Request $request): JsonResponse
    {
        $paginator = $this->service()->trashed(
            $this->indexParams($request),
            perPage: $this->perPageParam($request),
        )['data'];

        return $this->paginatedResponse($paginator, $request);
    }

    /**
     *     public function restore(Amenity $amenity, Request $request): JsonResponse
     *     {
     *         return $this->restoreResponse($amenity, $request);
     *     }
     */
    protected function restoreResponse(Model $model, Request $request): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->restore($model)),
            'custom.messages.restored',
            $request,
        );
    }

    /**
     *     public function forceDestroy(Amenity $amenity, Request $request): JsonResponse
     *     {
     *         return $this->forceDestroyResponse($amenity, $request);
     *     }
     */
    protected function forceDestroyResponse(Model $model, Request $request): JsonResponse
    {
        $result = $this->service()->forceDestroy($model);

        return $this->success(null, 'custom.messages.deleted', $result['code'], $request);
    }
}
