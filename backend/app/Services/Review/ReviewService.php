<?php

namespace App\Services\Review;

use App\Actions\Review\SetReviewPublishedAction;
use App\Actions\Review\SubmitReviewAction;
use App\Enums\ReviewableType;
use App\Exceptions\NotFoundException;
use App\Models\Guest;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;

class ReviewService
{
    protected array $with = ['guest'];
    protected int $perPage = 15;

    public function __construct(
        private readonly SubmitReviewAction       $submit,
        private readonly SetReviewPublishedAction $setPublished,
    ) {}

    /**
     * Resolve the `{type}/{uuid}` route pair to a model.
     *
     * Enum-gated so an arbitrary class name can never reach the query, and
     * NotFound (not 422) on a bad type so the endpoint reads as one resource.
     */
    public function resolveReviewable(string $type, string $uuid): Model
    {
        $reviewable = ReviewableType::tryFrom($type);
        if (! $reviewable) {
            throw new NotFoundException();
        }

        return $reviewable->modelClass()::where('uuid', $uuid)->firstOr(
            fn () => throw new NotFoundException(),
        );
    }

    public function indexFor(Model $reviewable): array
    {
        $data = Review::query()
            ->with($this->with)
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->getKey())
            ->where('is_published', true)
            ->latest()
            ->paginate($this->perPage);

        return ['data' => $data, 'code' => 200];
    }

    public function adminIndex(?bool $isPublished = null): array
    {
        $data = Review::query()
            ->with([...$this->with, 'reviewable'])
            ->when($isPublished !== null, fn ($q) => $q->where('is_published', $isPublished))
            ->latest()
            ->paginate($this->perPage);

        return ['data' => $data, 'code' => 200];
    }

    public function store(Guest $guest, Model $reviewable, array $data): array
    {
        return $this->submit->handle($guest, $reviewable, $data);
    }

    public function publish(Review $review, bool $isPublished): array
    {
        return $this->setPublished->handle($review, $isPublished);
    }
}
