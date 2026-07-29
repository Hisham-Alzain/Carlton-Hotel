<?php

namespace App\Base;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;

abstract class BaseController extends Controller
{
    use AuthorizesRequests;
    protected function success(
        mixed $data = null,
        string $messageKey = 'custom.messages.success',
        int $code = 200,
        ?Request $request = null
    ): JsonResponse {
        $req = $request ?? App::make(Request::class);
        return response()->json([
            'success'    => true,
            'message'    => __($messageKey),
            'data'       => $data,
            'request_id' => $req->attributes->get('request_id', ''),
        ], $code);
    }

    protected function paginatedSuccess(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        Request $request
    ): JsonResponse {
        return $this->success([
            'items' => $resourceClass::collection($paginator->getCollection()),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'custom.messages.success', 200, $request);
    }

    /**
     * The page size the client asked for, or null when it did not ask. The
     * default and the ceiling belong to the service — the controller's only job
     * is to read the request, because services never touch `request()`.
     */
    protected function perPageParam(Request $request): ?int
    {
        return $request->has('per_page') ? $request->integer('per_page') : null;
    }

    /**
     * Query-string params (filters, `search`, `sort`) handed down to the
     * service's filter. Whitelisting happens in the filter's `$safeParms`, so
     * passing the raw query array here is safe.
     *
     * @return array<string, mixed>
     */
    protected function indexParams(Request $request): array
    {
        return $request->query();
    }

    protected function respondFromService(
        array $result,
        string $messageKey = 'custom.messages.success',
        ?Request $request = null
    ): JsonResponse {
        $data = $result['data'];
        $code = $result['code'];

        if ($data instanceof LengthAwarePaginator) {
            $req = $request ?? App::make(Request::class);
            $data = (new BaseCollection($data))->toArray($req);
        }

        $msgKey = $code === 201 ? 'custom.messages.created' : $messageKey;
        return $this->success($data, $msgKey, $code, $request);
    }
}
