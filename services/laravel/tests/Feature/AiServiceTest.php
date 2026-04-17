<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;

class AiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset config
        config(['grpc.python_service_host' => 'python-service:50051']);
        config(['grpc.service_token' => 'test-token']);
        config(['grpc.timeout_ms' => 5000]);
    }

    /** @test */
    public function health_endpoint_returns_status(): void
    {
        Http::fake([
            'python-service:50051/health' => Http::response(['status' => 'ok', 'service' => 'ai'], 200),
        ]);

        $response = $this->getJson('/api/ai/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'grpc_available']);
    }

    /** @test */
    public function analyze_text_returns_suggestion(): void
    {
        Http::fake([
            'python-service:50051/api/analyze/text' => Http::response([
                'suggestion_id' => 'sug-123',
                'diagram_id' => 'diag-456',
                'mermaid_code' => 'graph TD; A --> B',
                'explanation' => 'Simple flow',
                'confidence' => 0.95,
                'sources' => ['doc1.md'],
            ], 200),
        ]);

        $response = $this->postJson('/api/ai/analyze/diag-456', [
            'mermaid_code' => 'flowchart TD; A --> B',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'suggestion_id',
                'diagram_id',
                'mermaid_code',
                'explanation',
                'confidence',
                'sources',
            ])
            ->assertJson([
                'diagram_id' => 'diag-456',
                'mermaid_code' => 'graph TD; A --> B',
            ]);
    }

    /** @test */
    public function analyze_text_validates_input(): void
    {
        $response = $this->postJson('/api/ai/analyze/test-diagram', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mermaid_code']);
    }

    /** @test */
    public function analyze_text_returns_graceful_fallback_on_service_error(): void
    {
        Http::fake([
            'python-service:50051/api/analyze/text' => Http::response('Service unavailable', 503),
        ]);

        $response = $this->postJson('/api/ai/analyze/diag-789', [
            'mermaid_code' => 'flowchart TD; X --> Y',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'fallback' => true,
            ]);
    }

    /** @test */
    public function ai_suggest_endpoint_uses_multimodal_client(): void
    {
        Http::fake([
            'python-service:50051/api/analyze/text' => Http::response([
                'suggestion_id' => 'sug-abc',
                'diagram_id' => 'diag-xyz',
                'mermaid_code' => 'improved graph',
                'explanation' => 'Better structure',
                'confidence' => 0.88,
                'sources' => [],
            ], 200),
        ]);

        $response = $this->postJson('/api/diagrams/diag-xyz/ai-suggest', [
            'mermaid_code' => 'original code',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'suggestion_id',
                'mermaid_code',
                'explanation',
                'confidence',
            ]);
    }

    protected function tearDown(): void
    {
        Http::fakeReset();
        Mockery::close();
        parent::tearDown();
    }
}
