<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class AiServiceTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
        
        // Reset config
        config(['grpc.python_service_host' => 'python-service:8001']);
        config(['grpc.service_token' => 'test-token']);
        config(['grpc.timeout_ms' => 8000]);
    }

    /** @test */
    public function health_endpoint_returns_status(): void
    {
        Http::fake([
            'python-service:8001/health' => Http::response(['status' => 'ok', 'service' => 'ai'], 200),
        ]);

        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/ai/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'grpc_available']);
    }

    /** @test */
    public function analyze_text_returns_suggestion(): void
    {
        Http::fake([
            'python-service:8001/api/analyze/text' => Http::response([
                'suggestion_id' => 'sug-123',
                'diagram_id' => 'diag-456',
                'mermaid_code' => 'graph TD; A --> B',
                'explanation' => 'Simple flow',
                'confidence' => 0.95,
                'sources' => ['doc1.md'],
            ], 200),
        ]);

        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/ai/analyze/diag-456', [
            'content' => 'flowchart TD; A --> B',
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
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/ai/analyze/test-diagram', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /** @test */
    public function analyze_text_returns_error_on_service_failure(): void
    {
        Http::fake([
            'python-service:8001/api/analyze/text' => Http::response('Service unavailable', 503),
        ]);

        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/ai/analyze/diag-789', [
            'content' => 'flowchart TD; X --> Y',
        ]);

        // Service returns error status when downstream fails
        $response->assertStatus(503)
            ->assertJsonStructure(['error']);
    }

    /** @test */
    public function ai_suggest_endpoint_exists(): void
    {
        Sanctum::actingAs($this->user);
        
        // Route exists - will fail with 403/404/500 depending on auth/model state
        $response = $this->postJson('/api/diagrams/01KPCWYP57ASVXP4H7K5M3EQPG/ai-suggest', [
            'prompt' => 'improve this flowchart',
        ]);

        // Route exists - should NOT return 404
        $this->assertNotEquals(404, $response->status());
    }
}
