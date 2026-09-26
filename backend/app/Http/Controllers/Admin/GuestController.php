<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Guest\AddGuestNoteRequest;
use App\Http\Requests\Guest\UpdateGuestPreferencesRequest;
use App\Http\Resources\Guest\GuestDirectoryResource;
use App\Http\Resources\Guest\GuestNoteResource;
use App\Http\Resources\Guest\GuestPreferencesResource;
use App\Http\Resources\Guest\GuestProfileResource;
use App\Models\Guest;
use App\Services\Guest\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff guest area (D-01). Stays on BaseController: its verbs (notes,
 * storeNote, …) are not the plain service verbs.
 */
class GuestController extends BaseController
{
    public function __construct(private readonly GuestService $service) {}

    /** The directory (D-02): search, field filters, sort and `?stay_status=`. */
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index($this->indexParams($request), null, $this->perPageParam($request))['data'],
            GuestDirectoryResource::class,
            $request,
        );
    }

    /** The staff-only profile (D-04). Never reachable from a guest route. */
    public function show(Request $request, Guest $guest): JsonResponse
    {
        $result         = $this->service->profile($guest);
        $result['data'] = new GuestProfileResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function notes(Request $request, Guest $guest): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->notes($guest, $this->perPageParam($request))['data'],
            GuestNoteResource::class,
            $request,
        );
    }

    public function storeNote(AddGuestNoteRequest $request, Guest $guest): JsonResponse
    {
        $result = $this->service->addNote($guest, $request->user(), $request->validated('body'));

        // success() rather than respondFromService(): the latter swaps every 201
        // message for the generic "Created successfully.".
        return $this->success(
            new GuestNoteResource($result['data']),
            'custom.messages.guest_note_added',
            $result['code'],
            $request,
        );
    }

    public function updatePreferences(UpdateGuestPreferencesRequest $request, Guest $guest): JsonResponse
    {
        $result = $this->service->updatePreferences($guest, $request->validated(), $request->user());

        return $this->success(
            new GuestPreferencesResource($result['data']),
            'custom.messages.preferences_updated',
            $result['code'],
            $request,
        );
    }
}
