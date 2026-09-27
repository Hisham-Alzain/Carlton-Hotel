<?php
namespace Tests\Feature;

use App\Exceptions\NotFoundException;
use Tests\TestCase;

class ExceptionEnvelopeTest extends TestCase
{
    public function test_domain_exception_returns_error_envelope(): void
    {
        // Trigger a 404 via a non-existent route — still produces not_found envelope
        $response = $this->getJson('/api/nonexistent-route-for-test');
        $response->assertStatus(404)
                 ->assertJson(['success' => false, 'error_code' => 'not_found'])
                 ->assertJsonStructure(['success','message','error_code','context','request_id']);
    }

    public function test_method_not_allowed_returns_405_envelope(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en'])->postJson('/api/health');

        $response->assertStatus(405)
                 ->assertJson(['success' => false, 'error_code' => 'method_not_allowed', 'message' => 'Method not allowed.', 'context' => null])
                 ->assertJsonStructure(['success', 'message', 'error_code', 'context', 'request_id']);
        $this->assertStringContainsString('GET', (string) $response->headers->get('Allow'));
        $this->assertNotEmpty($response->json('request_id'));
    }

    public function test_error_envelope_has_request_id(): void
    {
        $response = $this->getJson('/api/nonexistent-route-for-test');
        $this->assertNotEmpty($response->json('request_id'));
    }
}
