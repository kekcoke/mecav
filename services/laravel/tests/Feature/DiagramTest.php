<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Diagram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_diagrams_index()
    {
        $response = $this->actingAs($this->user)->get('/diagrams');
        $response->assertStatus(200);
    }

    public function test_user_can_view_create_diagram_page()
    {
        $response = $this->actingAs($this->user)->get('/diagrams/create');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_is_redirected()
    {
        $response = $this->get('/diagrams');
        $response->assertRedirect('/login');
    }

    public function test_user_can_create_diagram_via_api()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/diagrams', [
            'title' => 'Test Diagram',
            'diagram_type' => 'flowchart',
            'mermaid_code' => 'flowchart TD\n    Start --> End'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('title', 'Test Diagram');
        
        $this->assertDatabaseHas('diagrams', ['title' => 'Test Diagram']);
    }
}
