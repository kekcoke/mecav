<?php

namespace Tests\Unit;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagram_belongs_to_user()
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $diagram->user);
        $this->assertEquals($user->id, $diagram->user->id);
    }

    public function test_diagram_generates_ulid_on_creation()
    {
        $user = User::factory()->create();
        $diagram = Diagram::create([
            'user_id' => $user->id,
            'title' => 'Test',
            'diagram_type' => 'flowchart',
            'status' => 'draft',
            'tenant_id' => 'default'
        ]);

        $this->assertNotNull($diagram->ulid);
        $this->assertStringStartsWith('01', $diagram->ulid);
    }
}
