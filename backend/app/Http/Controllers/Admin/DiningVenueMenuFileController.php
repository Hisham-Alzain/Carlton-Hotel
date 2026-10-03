<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Dining\ReplaceVenueMenuFileAction;
use App\Base\BaseController;
use App\Http\Requests\Dining\UploadMenuFileRequest;
use App\Http\Resources\Cms\MediaResource;
use App\Models\DiningVenue;
use App\Services\Cms\DiningVenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A venue's downloadable menu file (Phase 8, D-11): one per venue, replaced on
 * upload. Gated `cms.edit` with the venue's image routes; the public read is
 * `GET /public/dining-venues/{venue}/menu/download`.
 */
class DiningVenueMenuFileController extends BaseController
{
    public function __construct(
        private readonly ReplaceVenueMenuFileAction $replaceMenuFile,
        private readonly DiningVenueService $venues,
    ) {}

    public function store(UploadMenuFileRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        $result = $this->replaceMenuFile->handle($diningVenue, $request->file('file'), $request->validated('title'));

        // success() rather than respondFromService(), which swaps every 201 for the generic "created" message.
        return $this->success(new MediaResource($result['data']), 'custom.messages.menu_file_uploaded', $result['code'], $request);
    }

    public function destroy(Request $request, DiningVenue $diningVenue): JsonResponse
    {
        return $this->respondFromService(
            $this->venues->removeMenuFile($diningVenue),
            'custom.messages.menu_file_removed',
            $request,
        );
    }
}
