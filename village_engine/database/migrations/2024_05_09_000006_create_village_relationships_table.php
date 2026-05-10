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
        Schema::create('village_relationships', function (Blueprint $table) {
            $table->id();
            
            // Related villages
            $table->foreignId('village_1_id')->constrained('villages')->onDelete('cascade');
            $table->foreignId('village_2_id')->constrained('villages')->onDelete('cascade');
            
            // Relationship strength (0.0 to 1.0) - acts as tension in force-directed graph
            $table->decimal('strength', 5, 4)->default(0.0); // Higher = stronger relationship = shorter rest length
            
            // Relationship type
            $table->enum('relationship_type', [
                'allied',        // Positive relationship
                'neutral',       // No special relationship
                'competitive',  // Competitive but not hostile
                'hostile'        // Negative relationship
            ])->default('neutral');
            
            // Relationship metrics
            $table->integer('resource_shares_count')->default(0); // P2P resource sharing instances
            $table->integer('user_migrations_count')->default(0); // Users moving between villages
            $table->decimal('trade_volume', 12, 2)->default(0.0); // Economic activity between villages
            
            // Trust and reputation between villages
            $table->decimal('trust_level', 5, 4)->default(0.5); // 0.0 to 1.0
            $table->decimal('reputation_transfer_weight', 5, 4)->default(0.1); // How reputation transfers
            
            // Relationship history
            $table->timestamp('established_at');
            $table->timestamp('last_interaction_at')->nullable();
            $table->integer('days_since_interaction')->default(0);
            
            // Relationship dynamics
            $table->decimal('strength_change_rate', 5, 4)->default(0.0); // How strength is changing
            $table->json('interaction_history')->nullable(); // Recent interaction data
            
            // Force-directed graph parameters
            $table->decimal('rest_length', 8, 4)->default(100.0); // Optimal distance in 3D space
            $table->decimal('spring_constant', 5, 4)->default(0.1); // Spring stiffness
            
            $table->timestamps();
            
            // Prevent duplicate relationships (ensure village_1_id < village_2_id for consistency)
            $table->unique(['village_1_id', 'village_2_id'], 'village_relationship_unique');
            
            // Indexes for performance
            $table->index('strength');
            $table->index('relationship_type');
            $table->index('trust_level');
            $table->index('last_interaction_at');
            
            // Composite indexes for common queries
            $table->index(['village_1_id', 'strength']);
            $table->index(['village_2_id', 'strength']);
            $table->index(['relationship_type', 'strength']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('village_relationships');
    }
};
