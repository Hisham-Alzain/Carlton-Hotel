<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\UploadMediaRequest;
use App\Http\Resources\Cms\MediaResource;
use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Facility;
use App\Models\Media;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
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

    private function delete(Request $request, Media $media): JsonResponse
    {
        $this->service->destroy($media);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function storeRoomType(UploadMediaRequest $request, RoomType $roomType): JsonResponse
    {
        return $this->upload($request, $roomType);
    }

    public function destroyRoomType(Request $request, RoomType $roomType, Media $media): JsonResponse
    {
        return $this->delete($request, $media);
    }

    public function storeRoom(UploadMediaRequest $request, Room $room): JsonResponse
    {
        return $this->upload($request, $room);
    }

    public function destroyRoom(Request $request, Room $room, Media $media): JsonResponse
    {
        return $this->delete($request, $media);
    }

    public function storeFacility(UploadMediaRequest $request, Facility $facility): JsonResponse
    {
        return $this->upload($request, $facility);
    }

    public function destroyFacility(Request $request, Facility $facility, Media $media): JsonResponse
    {
        return $this->delete($request, $media);
    }

    public function storeDiningVenue(UploadMediaRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        return $this->upload($request, $diningVenue);
    }

    public function destroyDiningVenue(Request $request, DiningVenue $diningVenue, Media $media): JsonResponse
    {
        return $this->delete($request, $media);
    }

    public function storeEventSpace(UploadMediaRequest $request, EventSpace $eventSpace): JsonResponse
    {
        return $this->upload($request, $eventSpace);
    }

    public function destroyEventSpace(Request $request, EventSpace $eventSpace, Media $media): JsonResponse
    {
        return $this->delete($request, $media);
    }

    public function storePromotion(UploadMediaRequest $request, Promotion $promotion): JsonResponse
    {
        return $this->upload($request, $promotion);
    }

    public function destroyPromotion(Request $request, Promotion $promotion, Media $media): JsonResponse
    {
        return $this->delete($request, $media);
    }
}
