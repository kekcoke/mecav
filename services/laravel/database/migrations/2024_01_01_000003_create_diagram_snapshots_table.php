<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('diagram_snapshots', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->integer('version')->default(1);
            $table->string('label')->nullable();         // human-readable e.g. "Before AI refactor"
            $table->longText('mermaid_code');
            $table->json('metadata')->nullable();        // diff_size, source: manual|auto|ai
            $table->bigInteger('storage_bytes')->default(0);
            $table->boolean('is_exported')->default(false);
            $table->string('export_path')->nullable();   // S3/local path for exported file
            $table->timestamp('expires_at')->nullable(); // null = permanent (within quota)
            $table->timestamp('created_at');

            $table->index(['diagram_id', 'version']);
            $table->index(['diagram_id', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_snapshots');
    }
};
