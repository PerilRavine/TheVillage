<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Village metadata
            $table->string('theme')->default('default'); // Visual theme for 3D space
            $table->json('settings')->nullable(); // Village-specific settings
            
            // Reputation and trust metrics
            $table->decimal('reputation_score', 8, 4)->default(0.0); // Village's global reputation
            $table->integer('population')->default(0); // Current active denizen count
            $table->integer('max_population')->default(150); // 3 degrees of separation limit
            
            // Village status
            $table->enum('status', ['forming', 'active', 'dormant', 'dissolved'])->default('forming');
            $table->timestamp('founded_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            
            // Geographic/position data for 3D map
            $table->decimal('map_x', 10, 4)->nullable(); // X position on force-directed graph
            $table->decimal('map_y', 10, 4)->nullable(); // Y position on force-directed graph
            $table->decimal('map_z', 10, 4)->nullable(); // Z position on force-directed graph
            
            // Village leadership
            $table->foreignId('elder_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            // Access control
            $table->boolean('public_access')->default(true);
            $table->decimal('minimum_reputation_to_join', 8, 4)->default(0.0);
            $table->boolean('identity_verification_required')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('reputation_score');
            $table->index('population');
            $table->index('elder_id');
            $table->index('created_by');
            $table->index(['map_x', 'map_y', 'map_z']); // For spatial queries
            
            // Full-text search (SQLite doesn't support fullText)
            // $table->fullText(['name', 'description']);
            // MySQL requires prefix length for TEXT columns in indexes
            $table->index('name');
            // Remove description index for MySQL compatibility
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
