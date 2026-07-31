<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only half of the declarative controller pair. A child declares
 * `$resource` and returns its service from `service()`.
 *
 * `index()` is inherited whole — it takes no route-bound model and no
 * FormRequest, so nothing stands between it and a live route.
 *
 * `show()` is **not** declared here, and that is deliberate. It used to be, with
 * an `Illuminate\Database\Eloquent\Model` type-hint, which made the class
 * impossible to extend for a real route: implicit route-model binding resolves
 * `{amenity}` by matching the *route* parameter name to the *method* parameter
 * name and then calling `$container->make()` on the declared class — and
 * `Model` is abstract, so it cannot be made. A child could not fix that by
 * narrowing the type either: PHP's parameter contravariance rule makes
 * `show(Amenity $a)` overriding `show(Model $m)` a fatal error, as does
 * renaming/reordering the parameters into the shape the routes use.
 *
 * So the base owns the *body* (`showResponse()`) and the child owns the
 * *signature*. The child's `show(Amenity $amenity, Request $request)` keeps
 * implicit binding, `{journalPost:slug}` binding fields, `->withTrashed()` and
 * scoped bindings working exactly as Laravel intends, and the duplicated
 * resource-wrapping body disappears.
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

        return $this->paginatedResponse($paginator, $request);
    }

    /**
     * The body of a `show()` route. The child declares the concrete signature
     * and calls this:
     *
     *     public function show(Amenity $amenity, Request $request): JsonResponse
     *     {
     *         return $this->showResponse($amenity, $request);
     *     }
     */
    protected function showResponse(Model $model, Request $request): JsonResponse
    {
        return $this->respondFromService(
            $this->shapeResource($this->service()->show($model)),
            request: $request,
        );
    }

    /**
     * `paginatedSuccess()` is the same call the hand-written controllers make,
     * so a controller that moves onto this base class emits a byte-identical
     * envelope.
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, Request $request): JsonResponse
    {
        if ($this->resource !== null) {
            return $this->paginatedSuccess($paginator, $this->resource, $request);
        }

        return $this->respondFromService(['data' => $paginator, 'code' => 200], request: $request);
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
