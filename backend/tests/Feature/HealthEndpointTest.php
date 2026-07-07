<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_returns_success_envelope(): void
    {
        $this->getJson('/api/health')
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'message', 'data', 'request_id'])
             ->assertJson(['success' => true])
             ->assertJsonPath('data.status', 'ok');
    }

    public function test_request_id_header_matches_body(): void
    {
        $response = $this->getJson('/api/health');
        $this->assertSame($response->json('request_id'), $response->headers->get('X-Request-Id'));
    }
}
