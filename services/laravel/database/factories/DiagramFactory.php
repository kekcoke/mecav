<?php

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Diagram>
 */
class DiagramFactory extends Factory
{
    protected $model = Diagram::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ulid' => Str::ulid()->toBase32(),
            'tenant_id' => Str::ulid()->toBase32(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'mermaid_code' => 'flowchart TD; A --> B; B --> C;',
            'diagram_type' => 'flowchart',
            'status' => 'draft',
            'ai_enabled' => true,
            'tags' => [],
            'export_formats' => [],
            'storage_bytes' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_enabled' => false,
        ]);
    }
}
