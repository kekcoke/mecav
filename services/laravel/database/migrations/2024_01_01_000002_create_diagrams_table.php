<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('diagrams', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('mermaid_code');           // current HEAD state
            $table->string('diagram_type')->default('flowchart'); // flowchart|sequence|class|erd|gantt
            $table->string('status')->default('draft'); // draft|published|archived
            $table->boolean('ai_enabled')->default(false);
            $table->json('tags')->nullable();
            $table->json('export_formats')->nullable();  // ['png','svg','pdf']
            $table->bigInteger('storage_bytes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagrams');
    }
};
