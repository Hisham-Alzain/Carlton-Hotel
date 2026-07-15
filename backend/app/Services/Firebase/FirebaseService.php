<?php

namespace App\Services\Firebase;

use App\Contracts\FirebaseServiceInterface;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;

// Real Firebase Admin SDK wiring. Not exercised by the test suite (no live
// credentials in dev/CI) — tests rebind FirebaseServiceInterface to a fake,
// same as P5's ManualDriver-only PaymentGatewayInterface.
class FirebaseService implements FirebaseServiceInterface
{
    public function sendPush(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_filter($tokens));
        if (empty($tokens)) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body))
            ->withData(array_map('strval', $data));

        Firebase::messaging()->sendMulticast($message, $tokens);
    }

    public function mirrorToFirestore(string $collection, string $documentId, array $data): void
    {
        Firebase::firestore()->database()
            ->collection($collection)
            ->document($documentId)
            ->set($data, ['merge' => true]);
    }
}
