<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseIndexController extends BaseController
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
}
