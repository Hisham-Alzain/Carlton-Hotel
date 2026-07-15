<?php

namespace Tests\Support;

use App\Contracts\FirebaseServiceInterface;

class FakeFirebaseService implements FirebaseServiceInterface
{
    /** @var array<int, array{tokens: array<int,string>, title: string, body: string, data: array}> */
    public array $pushes = [];

    /** @var array<int, array{collection: string, document: string, data: array}> */
    public array $mirrors = [];

    public function sendPush(array $tokens, string $title, string $body, array $data = []): void
    {
        $this->pushes[] = compact('tokens', 'title', 'body', 'data');
    }

    public function mirrorToFirestore(string $collection, string $documentId, array $data): void
    {
        $this->mirrors[] = ['collection' => $collection, 'document' => $documentId, 'data' => $data];
    }
}
