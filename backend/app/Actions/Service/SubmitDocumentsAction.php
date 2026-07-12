<?php

namespace App\Actions\Service;

use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\Reservation;
use App\Traits\FileTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SubmitDocumentsAction
{
    use FileTrait;

    // $documents: array<int, array{type: string, file: UploadedFile}>
    public function handle(Guest $guest, Reservation $reservation, array $documents): array
    {
        return DB::transaction(function () use ($guest, $reservation, $documents) {
            $stored = [];
            foreach ($documents as $doc) {
                /** @var UploadedFile $file */
                $file = $doc['file'];
                $dir  = 'guest-documents/' . $guest->uuid . '/' . $reservation->uuid;
                $path = $this->storeFile($file, $dir);

                $stored[] = GuestDocument::create([
                    'guest_id'       => $guest->id,
                    'reservation_id' => $reservation->id,
                    'type'           => $doc['type'],
                    'file_path'      => $path,
                ]);
            }

            CheckInApproval::updateOrCreate(
                ['reservation_id' => $reservation->id],
                ['status' => CheckInApproval::STATUS_PENDING, 'approved_by' => null]
            );

            return ['data' => collect($stored), 'code' => 201];
        });
    }
}
