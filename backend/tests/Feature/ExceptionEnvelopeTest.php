<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExceptionEnvelopeTest extends TestCase
{
    public function test_domain_exception_returns_error_envelope(): void
    {
        $this->getJson('/api/probe/domain-exception')
             ->assertStatus(404)
             ->assertJson(['success' => false, 'error_code' => 'not_found'])
             ->assertJsonStructure(['success', 'message', 'error_code', 'context', 'request_id']);
    }

    public function test_error_envelope_has_request_id(): void
    {
        $response = $this->getJson('/api/probe/domain-exception');
        $this->assertNotEmpty($response->json('request_id'));
    }
}
