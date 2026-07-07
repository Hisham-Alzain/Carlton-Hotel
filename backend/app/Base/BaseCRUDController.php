<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseCRUDController extends BaseController
{
    abstract protected function service(): BaseService;

    public function index(Request $request): JsonResponse
    {
        return $this->respondFromService($this->service()->index(), request: $request);
    }

    public function show(Model $model, Request $request): JsonResponse
    {
        return $this->respondFromService($this->service()->show($model), request: $request);
    }

    public function store(BaseRequest $request): JsonResponse
    {
        return $this->respondFromService($this->service()->store($request->validated()), request: $request);
    }

    public function update(BaseRequest $request, Model $model): JsonResponse
    {
        return $this->respondFromService($this->service()->update($model, $request->validated()), request: $request);
    }

    public function destroy(Model $model, Request $request): JsonResponse
    {
        $result = $this->service()->destroy($model);
        return $this->success(null, 'custom.messages.deleted', $result['code'], $request);
    }
}
