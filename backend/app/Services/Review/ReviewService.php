<?php

namespace App\Services\Review;

use App\Actions\Review\SetReviewPublishedAction;
use App\Actions\Review\SubmitReviewAction;
use App\Enums\ReviewableType;
use App\Exceptions\NotFoundException;
use App\Filters\ReviewFilter;
use App\Models\Guest;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;

class ReviewService
{
    protected array $with = ['guest'];
    protected int $perPage = 15;

    /** Same ceiling as `BaseService`, for the same reason. */
    protected int $maxPerPage = 100;

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

    /**
     * The moderation queue.
     *
     * Filtering goes through `ReviewFilter` instead of a hand-read `?is_published`
     * boolean. The old signature took a `?bool` the controller produced with
     * `$request->boolean('is_published')`, which reads an empty `?is_published=`
     * as `false` — so the "Status: All" option of a select returned only drafts,
     * and `?is_published=trve` returned the drafts too rather than saying no. The
     * filter layer already owns those three answers for every other list screen
     * (see `BaseFilter`'s three rules), and this one now shares them.
     *
     * @param  array<string, mixed>  $params   Query-string params from the controller.
     * @param  int|null              $perPage  Client-requested page size, or null.
     */
    public function adminIndex(array $params = [], ?int $perPage = null): array
    {
        $query = Review::query()
            ->with([...$this->with, 'reviewable'])
            ->latest();

        (new ReviewFilter($params))->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    /**
     * Clamp the client's page size into `[1, $maxPerPage]`.
     *
     * Mirrors `BaseService::resolvePerPage()`. This service is deliberately not a
     * `BaseService` — it has no `$model` and every read is a purpose-built
     * projection rather than declarative CRUD — so the clamp is restated rather
     * than inherited. Without it `?per_page=100000` is one query for every review
     * the hotel has ever received, plus its guest and reviewable eager loads.
     */
    private function resolvePerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return $this->perPage;
        }

        return min($perPage, $this->maxPerPage);
    }

    /**
     * One review, opened from the moderation queue.
     *
     * No `is_published` condition, unlike `indexFor()`: an unpublished review is
     * precisely the kind a moderator opens this route to read, and hiding it
     * would make the drafts the queue lists unopenable — moderation is the point
     * of the endpoint. The read gate (`cms.view|cms.edit`) is what keeps it off
     * the public site; the published-only rule belongs to the public projection,
     * not to this one.
     *
     * `loadMissing` rather than a fresh lookup — the route already resolved the
     * model by uuid — and it is not optional: `ReviewResource` guards `author`
     * with `whenLoaded('guest')`, so without the eager load the moderator gets a
     * comment with nobody attached to it. `reviewable` matches `adminIndex()`,
     * which the same screen renders.
     */
    public function show(Review $review): array
    {
        return ['data' => $review->loadMissing([...$this->with, 'reviewable']), 'code' => 200];
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
