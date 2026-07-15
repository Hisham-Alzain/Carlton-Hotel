<?php

namespace App\Traits;

use App\Contracts\FirebaseServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

trait MirrorsToFirestore
{
    /**
     * Best-effort: the DB write this follows has already committed and is the
     * source of truth. Firestore is a live-view mirror only — a mirror
     * failure (network blip, quota, credentials) must not turn an already-
     * successful request into a 500, and must not be retried by the caller
     * (which would otherwise duplicate the underlying DB write on retry).
     *
     * @param array<string, mixed> $data
     */
    protected function mirrorToFirestore(string $collection, string $documentId, array $data): void
    {
        try {
            app(FirebaseServiceInterface::class)->mirrorToFirestore($collection, $documentId, $data);
        } catch (Throwable $e) {
            Log::warning('Firestore mirror failed', [
                'collection' => $collection,
                'document_id' => $documentId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
