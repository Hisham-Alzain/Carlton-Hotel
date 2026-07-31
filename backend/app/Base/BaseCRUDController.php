<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Full declarative CRUD controller: `index()` inherited whole, plus the bodies
 * of `show`/`store`/`update`/`destroy`. A child declares `$resource`, returns
 * its service from `service()`, and writes one typed line per write verb.
 *
 * The write verbs are **not** declared here as public methods, for the same
 * reason `show()` is not declared on `BaseIndexController` (see the note there),
 * plus a second one specific to validation: `store()`/`update()` used to
 * type-hint the abstract `App\Base\BaseRequest`, which named no concrete
 * FormRequest for a route to validate against — and again, a child could not
 * narrow it to `CreateAmenityRequest` without a fatal error, so the rules a
 * route enforced could never be declared. Naming the FormRequest in the child's
 * signature is also the only shape in which Laravel resolves and validates it
 * before the method body runs.
 *
 * `BaseRequest` remains the parameter type on `storeResponse()`/
 * `updateResponse()`: the child passes an already-resolved concrete instance,
 * so no container resolution and no variance rule is involved.
 */
abstract class BaseCRUDController extends BaseIndexController
{
    /**
     *     public function store(CreateAmenityRequest $request): JsonResponse
     *     {
     *         return $this->storeResponse($request);
     *     }
     */
    protected function storeResponse(BaseRequest $request): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->store($request->validated())),
            request: $request,
        );
    }

    /**
     *     public function update(UpdateAmenityRequest $request, Amenity $amenity): JsonResponse
     *     {
     *         return $this->updateResponse($request, $amenity);
     *     }
     */
    protected function updateResponse(BaseRequest $request, Model $model): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->update($model, $request->validated())),
            request: $request,
        );
    }

    /**
     *     public function destroy(Amenity $amenity, Request $request): JsonResponse
     *     {
     *         return $this->destroyResponse($amenity, $request);
     *     }
     */
    protected function destroyResponse(Model $model, Request $request): JsonResponse
    {
        $result = $this->service()->destroy($model);

        return $this->success(null, 'custom.messages.deleted', $result['code'], $request);
    }
}
