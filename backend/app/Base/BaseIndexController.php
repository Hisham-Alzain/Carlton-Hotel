<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only half of the declarative controller pair: `index` + `show`, no write
 * verbs. A child declares `$resource` and returns its service from `service()`.
 *
 * NOTE: nothing in `app/Http/Controllers` extends this yet — all 25 controllers
 * hand-copy the index one-liner instead. See `tests/Unit/BaseControllerPlumbingTest.php`,
 * which exercises this class through a test-only subclass so the `per_page` and
 * filter plumbing is not shipped untested.
 */
abstract class BaseIndexController extends BaseController
{
    /**
     * Resource the payload is shaped with. Left null the raw models are
     * serialised, which is almost never what an endpoint wants — declare it.
     *
     * @var class-string<BaseResource>|null
     */
    protected ?string $resource = null;

    abstract protected function service(): BaseService;

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service()->index(
            $this->indexParams($request),
            perPage: $this->perPageParam($request),
        )['data'];

        // `paginatedSuccess()` is the same call the hand-written controllers
        // make, so a controller that moves onto this base class emits a
        // byte-identical envelope.
        if ($this->resource !== null) {
            return $this->paginatedSuccess($paginator, $this->resource, $request);
        }

        return $this->respondFromService(['data' => $paginator, 'code' => 200], request: $request);
    }

    public function show(Model $model, Request $request): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->show($model)),
            request: $request,
        );
    }

    /**
     * @param  array{data: mixed, code: int}  $result
     * @return array{data: mixed, code: int}
     */
    protected function shapeResource(array $result): array
    {
        if ($this->resource !== null && $result['data'] instanceof Model) {
            $result['data'] = new ($this->resource)($result['data']);
        }

        return $result;
    }
}
