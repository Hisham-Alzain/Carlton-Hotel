<?php

namespace App\Services\Firebase;

use App\Contracts\FirebaseServiceInterface;

// Bound whenever no Firebase credentials are configured (local/CI/testing, and
// prod until credentials are provisioned) so push/mirror calls are safe no-ops
// instead of throwing — matches P0's "config published but not configured" note.
class NullFirebaseService implements FirebaseServiceInterface
{
    public function sendPush(array $tokens, string $title, string $body, array $data = []): void
    {
        //
    }

    public function mirrorToFirestore(string $collection, string $documentId, array $data): void
    {
        //
    }
}
