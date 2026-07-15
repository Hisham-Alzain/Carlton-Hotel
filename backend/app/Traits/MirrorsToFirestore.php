<?php

namespace App\Traits;

use App\Contracts\FirebaseServiceInterface;

trait MirrorsToFirestore
{
    /**
     * @param array<string, mixed> $data
     */
    protected function mirrorToFirestore(string $collection, string $documentId, array $data): void
    {
        app(FirebaseServiceInterface::class)->mirrorToFirestore($collection, $documentId, $data);
    }
}
