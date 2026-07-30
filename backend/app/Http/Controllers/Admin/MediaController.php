<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
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

    public function storeRoom(UploadMediaRequest $request, Room $room): JsonResponse
    {
        return $this->upload($request, $room);
    }

    public function destroyRoom(Request $request, Room $room, Media $media): JsonResponse
    {
        return $this->delete($request, $room, $media);
    }

    public function storeFacility(UploadMediaRequest $request, Facility $facility): JsonResponse
    {
        return $this->upload($request, $facility);
    }

    public function destroyFacility(Request $request, Facility $facility, Media $media): JsonResponse
    {
        return $this->delete($request, $facility, $media);
    }

    public function storeDiningVenue(UploadMediaRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        return $this->upload($request, $diningVenue);
    }

    public function destroyDiningVenue(Request $request, DiningVenue $diningVenue, Media $media): JsonResponse
    {
        return $this->delete($request, $diningVenue, $media);
    }

    public function storeEventSpace(UploadMediaRequest $request, EventSpace $eventSpace): JsonResponse
    {
        return $this->upload($request, $eventSpace);
    }

    public function destroyEventSpace(Request $request, EventSpace $eventSpace, Media $media): JsonResponse
    {
        return $this->delete($request, $eventSpace, $media);
    }

    public function storeMenuItem(UploadMediaRequest $request, MenuItem $menuItem): JsonResponse
    {
        return $this->upload($request, $menuItem);
    }

    public function destroyMenuItem(Request $request, MenuItem $menuItem, Media $media): JsonResponse
    {
        return $this->delete($request, $menuItem, $media);
    }

    public function storeHomeSlider(UploadMediaRequest $request, HomeSlider $homeSlider): JsonResponse
    {
        return $this->upload($request, $homeSlider);
    }

    public function destroyHomeSlider(Request $request, HomeSlider $homeSlider, Media $media): JsonResponse
    {
        return $this->delete($request, $homeSlider, $media);
    }

    public function storePromotion(UploadMediaRequest $request, Promotion $promotion): JsonResponse
    {
        return $this->upload($request, $promotion);
    }

    public function destroyPromotion(Request $request, Promotion $promotion, Media $media): JsonResponse
    {
        return $this->delete($request, $promotion, $media);
    }

    public function storeTestimonial(UploadMediaRequest $request, Testimonial $testimonial): JsonResponse
    {
        return $this->upload($request, $testimonial);
    }

    public function destroyTestimonial(Request $request, Testimonial $testimonial, Media $media): JsonResponse
    {
        return $this->delete($request, $testimonial, $media);
    }

    public function storeExperience(UploadMediaRequest $request, Experience $experience): JsonResponse
    {
        return $this->upload($request, $experience);
    }

    public function destroyExperience(Request $request, Experience $experience, Media $media): JsonResponse
    {
        return $this->delete($request, $experience, $media);
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

    /** First image (lowest sort_order) is the article's cover — see JournalPostResource. */
    public function storeJournalPost(UploadMediaRequest $request, JournalPost $journalPost): JsonResponse
    {
        return $this->upload($request, $journalPost);
    }

    public function destroyJournalPost(Request $request, JournalPost $journalPost, Media $media): JsonResponse
    {
        return $this->delete($request, $journalPost, $media);
    }
}
