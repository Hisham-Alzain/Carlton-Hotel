<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Http\Resources\Cms\TestimonialResource;
use App\Services\Cms\TestimonialService;

/**
 * The website renders testimonials as one section, so there is no public `show`
 * — no route needs a single quote by uuid. Add one only when a page actually
 * deep-links to it.
 */
class TestimonialController extends BasePublicIndexController
{
    protected ?string $resource = TestimonialResource::class;

    public function __construct(private readonly TestimonialService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }
}
