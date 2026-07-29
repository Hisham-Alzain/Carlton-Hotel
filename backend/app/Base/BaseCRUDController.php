<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Full declarative CRUD controller: `index`/`show` inherited, plus the three
 * write verbs. A child declares `$resource` and returns its service from
 * `service()`; validation stays in the injected `BaseRequest`.
 *
 * NOTE: nothing in `app/Http/Controllers` extends this yet — see the note on
 * `BaseIndexController` and `tests/Unit/BaseControllerPlumbingTest.php`.
 */
abstract class BaseCRUDController extends BaseIndexController
{
    public function store(BaseRequest $request): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->store($request->validated())),
            request: $request,
        );
    }

    public function update(BaseRequest $request, Model $model): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->update($model, $request->validated())),
            request: $request,
        );
    }

    public function destroy(Model $model, Request $request): JsonResponse
    {
        $result = $this->service()->destroy($model);

        return $this->success(null, 'custom.messages.deleted', $result['code'], $request);
    }
}
