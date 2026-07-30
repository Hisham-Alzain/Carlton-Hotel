<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\AttachMediaRequest;
use App\Http\Requests\Cms\UpdateMediaRequest;
use App\Http\Requests\Cms\UploadLibraryMediaRequest;
use App\Http\Requests\Cms\UploadMediaRequest;
use App\Http\Resources\Cms\MediaResource;
use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Experience;
use App\Models\Facility;
use App\Models\GalleryItem;
use App\Models\HomeSlider;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Testimonial;
use App\Services\Cms\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends BaseController
{
    public function __construct(private readonly MediaService $service) {}

    private function upload(UploadMediaRequest $request, Model $model): JsonResponse
    {
        $result = $this->service->attach(
            $model,
            $request->file('image'),
            (int) $request->input('sort_order', 0)
        );
        $result['data'] = new MediaResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    // ── The library itself ────────────────────────────────────────────────

    /**
     * Browse every asset, attached or not. Filterable by `mime`,
     * `mediable_type` and `unattached` — see MediaFilter.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index($this->indexParams($request), $this->perPageParam($request))['data'],
            MediaResource::class,
            $request,
        );
    }

    /** Upload into the library with no parent. */
    public function store(UploadLibraryMediaRequest $request): JsonResponse
    {
        $result = $this->service->upload(
            $request->file('image'),
            $request->safe()->except('image'),
        );
        $result['data'] = new MediaResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        $result = $this->service->update($media, $request->validated());
        $result['data'] = new MediaResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    /**
     * Delete an asset from the library screen. Unlike the nested routes this one
     * is addressed by media uuid alone, so there is no parent to scope against.
     */
    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->service->purge($media);
        // Same key as the nested delete: two routes onto one asset store should
        // not answer with two different messages.
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    /**
     * Place existing library assets on a parent. Returns the parent's new rows,
     * one per requested uuid, in request order.
     */
    private function attachExisting(AttachMediaRequest $request, Model $parent): JsonResponse
    {
        $result = $this->service->attachExisting($parent, $request->validated('media_uuids'));
        $result['data'] = MediaResource::collection($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    /**
     * $parent is load-bearing: the service refuses to delete media that is not
     * attached to it, so the {parent} segment of the route actually scopes the
     * delete instead of being decorative.
     */
    private function delete(Request $request, Model $parent, Media $media): JsonResponse
    {
        $this->service->destroy($parent, $media);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function storeRoomType(UploadMediaRequest $request, RoomType $roomType): JsonResponse
    {
        return $this->upload($request, $roomType);
    }

    public function destroyRoomType(Request $request, RoomType $roomType, Media $media): JsonResponse
    {
        return $this->delete($request, $roomType, $media);
    }

    public function attachRoomType(AttachMediaRequest $request, RoomType $roomType): JsonResponse
    {
        return $this->attachExisting($request, $roomType);
    }

    public function storeRoom(UploadMediaRequest $request, Room $room): JsonResponse
    {
        return $this->upload($request, $room);
    }

    public function destroyRoom(Request $request, Room $room, Media $media): JsonResponse
    {
        return $this->delete($request, $room, $media);
    }

    public function attachRoom(AttachMediaRequest $request, Room $room): JsonResponse
    {
        return $this->attachExisting($request, $room);
    }

    public function storeFacility(UploadMediaRequest $request, Facility $facility): JsonResponse
    {
        return $this->upload($request, $facility);
    }

    public function destroyFacility(Request $request, Facility $facility, Media $media): JsonResponse
    {
        return $this->delete($request, $facility, $media);
    }

    public function attachFacility(AttachMediaRequest $request, Facility $facility): JsonResponse
    {
        return $this->attachExisting($request, $facility);
    }

    public function storeDiningVenue(UploadMediaRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        return $this->upload($request, $diningVenue);
    }

    public function destroyDiningVenue(Request $request, DiningVenue $diningVenue, Media $media): JsonResponse
    {
        return $this->delete($request, $diningVenue, $media);
    }

    public function attachDiningVenue(AttachMediaRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        return $this->attachExisting($request, $diningVenue);
    }

    public function storeEventSpace(UploadMediaRequest $request, EventSpace $eventSpace): JsonResponse
    {
        return $this->upload($request, $eventSpace);
    }

    public function destroyEventSpace(Request $request, EventSpace $eventSpace, Media $media): JsonResponse
    {
        return $this->delete($request, $eventSpace, $media);
    }

    public function attachEventSpace(AttachMediaRequest $request, EventSpace $eventSpace): JsonResponse
    {
        return $this->attachExisting($request, $eventSpace);
    }

    public function storeMenuItem(UploadMediaRequest $request, MenuItem $menuItem): JsonResponse
    {
        return $this->upload($request, $menuItem);
    }

    public function destroyMenuItem(Request $request, MenuItem $menuItem, Media $media): JsonResponse
    {
        return $this->delete($request, $menuItem, $media);
    }

    public function attachMenuItem(AttachMediaRequest $request, MenuItem $menuItem): JsonResponse
    {
        return $this->attachExisting($request, $menuItem);
    }

    public function storeHomeSlider(UploadMediaRequest $request, HomeSlider $homeSlider): JsonResponse
    {
        return $this->upload($request, $homeSlider);
    }

    public function destroyHomeSlider(Request $request, HomeSlider $homeSlider, Media $media): JsonResponse
    {
        return $this->delete($request, $homeSlider, $media);
    }

    public function attachHomeSlider(AttachMediaRequest $request, HomeSlider $homeSlider): JsonResponse
    {
        return $this->attachExisting($request, $homeSlider);
    }

    public function storePromotion(UploadMediaRequest $request, Promotion $promotion): JsonResponse
    {
        return $this->upload($request, $promotion);
    }

    public function destroyPromotion(Request $request, Promotion $promotion, Media $media): JsonResponse
    {
        return $this->delete($request, $promotion, $media);
    }

    public function attachPromotion(AttachMediaRequest $request, Promotion $promotion): JsonResponse
    {
        return $this->attachExisting($request, $promotion);
    }

    public function storeTestimonial(UploadMediaRequest $request, Testimonial $testimonial): JsonResponse
    {
        return $this->upload($request, $testimonial);
    }

    public function destroyTestimonial(Request $request, Testimonial $testimonial, Media $media): JsonResponse
    {
        return $this->delete($request, $testimonial, $media);
    }

    public function attachTestimonial(AttachMediaRequest $request, Testimonial $testimonial): JsonResponse
    {
        return $this->attachExisting($request, $testimonial);
    }

    public function storeExperience(UploadMediaRequest $request, Experience $experience): JsonResponse
    {
        return $this->upload($request, $experience);
    }

    public function destroyExperience(Request $request, Experience $experience, Media $media): JsonResponse
    {
        return $this->delete($request, $experience, $media);
    }

    public function attachExperience(AttachMediaRequest $request, Experience $experience): JsonResponse
    {
        return $this->attachExisting($request, $experience);
    }

    /**
     * A gallery item is a photograph with a caption, so this route is how the
     * photograph gets there — the row is meaningless until it has one.
     */
    public function storeGalleryItem(UploadMediaRequest $request, GalleryItem $galleryItem): JsonResponse
    {
        return $this->upload($request, $galleryItem);
    }

    public function destroyGalleryItem(Request $request, GalleryItem $galleryItem, Media $media): JsonResponse
    {
        return $this->delete($request, $galleryItem, $media);
    }

    public function attachGalleryItem(AttachMediaRequest $request, GalleryItem $galleryItem): JsonResponse
    {
        return $this->attachExisting($request, $galleryItem);
    }

    /** First image (lowest sort_order) is the article's cover — see JournalPostResource. */
    public function storeJournalPost(UploadMediaRequest $request, JournalPost $journalPost): JsonResponse
    {
        return $this->upload($request, $journalPost);
    }

    public function destroyJournalPost(Request $request, JournalPost $journalPost, Media $media): JsonResponse
    {
        return $this->delete($request, $journalPost, $media);
    }

    public function attachJournalPost(AttachMediaRequest $request, JournalPost $journalPost): JsonResponse
    {
        return $this->attachExisting($request, $journalPost);
    }
}
