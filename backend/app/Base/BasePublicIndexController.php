<?php

namespace App\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public read half: one list endpoint, served from `indexPublic()`.
 *
 * `index()` is overridden rather than inherited, and the override is the whole
 * point of the class. `BaseIndexController::index()` hands
 * `indexParams($request)` — the raw query string — to the service's filter,
 * which is right for a staff list and wrong for a public one: it would let
 * `?is_active=false` on `/api/public/room-types` widen an anonymous list to
 * unpublished content. `BaseService::indexPublic()` takes a page size and
 * nothing else, so the only thing this class can read off the query string is
 * `per_page`.
 *
 * That was previously a convention held up by twelve hand-copied one-liners and
 * one test on one resource. Here it is a property of the class every public
 * list controller extends. `PublicIndexBoundaryTest` pins it.
 *
 * `show()` is deliberately not provided: a public `show` is not a plain read,
 * it is a read plus a visibility check (`is_active`, 404 otherwise), and that
 * belongs to the resource, not the base. Public controllers declare their own
 * and call `showResponse()` after the check.
 */
abstract class BasePublicIndexController extends BaseIndexController
{
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service()->indexPublic($this->perPageParam($request))['data'];

        return $this->paginatedResponse($paginator, $request);
    }
}
