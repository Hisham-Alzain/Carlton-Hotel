<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Http\Resources\Cms\FaqResource;
use App\Services\Cms\FaqService;

/**
 * The website renders the FAQ as one accordion, so there is no public `show` —
 * no page deep-links to a single question.
 */
class FaqController extends BasePublicIndexController
{
    protected ?string $resource = FaqResource::class;

    public function __construct(private readonly FaqService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }
}
