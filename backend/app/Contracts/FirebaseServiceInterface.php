<?php

namespace App\Contracts;

interface FirebaseServiceInterface
{
    /**
     * @param array<int, string> $tokens
     * @param array<string, mixed> $data
     */
    public function sendPush(array $tokens, string $title, string $body, array $data = []): void;

    /**
     * @param array<string, mixed> $data
     */
    public function mirrorToFirestore(string $collection, string $documentId, array $data): void;
}
