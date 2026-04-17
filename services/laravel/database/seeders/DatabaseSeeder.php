<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users — INSERT ... ON CONFLICT DO NOTHING (IF EXISTS)
        DB::statement("
            INSERT INTO users (ulid, name, email, password, role, tenant_id, created_at, updated_at)
            VALUES 
                ('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'Test User', 'test@mecav.local', ?, 'editor', 'tenant-default', NOW(), NOW()),
                ('01ARZ3NDEKTSV4RRFFQ69G5FAW', 'Admin User', 'admin@mecav.local', ?, 'admin', 'tenant-default', NOW(), NOW())
            ON CONFLICT (ulid) DO NOTHING
        ", [Hash::make('password'), Hash::make('admin123')]);

        // Diagrams — INSERT ... ON CONFLICT DO NOTHING
        DB::statement("
            INSERT INTO diagrams (ulid, user_id, tenant_id, title, mermaid_code, diagram_type, status, created_at, updated_at)
            VALUES 
                ('01ARZ3NDEKTSV4RRFFQ69G5FAX', 1, 'tenant-default', 'Sample Flowchart', 'flowchart TD\\n    A[Start] --> B{Decision}\\n    B -->|Yes| C[Process]\\n    B -->|No| D[End]', 'flowchart', 'published', NOW(), NOW()),
                ('01ARZ3NDEKTSV4RRFFQ69G5FAY', 1, 'tenant-default', 'Sample Sequence', 'sequenceDiagram\\n    participant U as User\\n    participant S as System\\n    U->>S: Request\\n    S-->>U: Response', 'sequence', 'draft', NOW(), NOW())
            ON CONFLICT (ulid) DO NOTHING
        ");
    }
}
