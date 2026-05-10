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
        Schema::create('contextual_reputations', function (Blueprint $table) {
            $table->id();
            
            // User and context
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('context_type', ['village', 'map', 'global']);
            $table->unsignedBigInteger('context_id')->nullable(); // village_id or null for map/global
            
            // Contextual reputation score (0-100)
            $table->decimal('reputation_score', 8, 4)->default(0.0);
            
            // Contextual factors that contribute to reputation
            $table->decimal('interaction_score', 8, 4)->default(0.0); // Direct interactions (40% weight)
            $table->decimal('vouch_weight', 8, 4)->default(0.0); // Vouches received (30% weight)
            $table->decimal('penalty_weight', 8, 4)->default(0.0); // Breaches/betrayals (20% weight, negative)
            $table->decimal('time_weight', 8, 4)->default(0.0); // Time investment (10% weight)
            
            // Context-specific role and status
            $table->enum('role_in_context', [
                'stranger',
                'sojourner', 
                'denizen',
                'steward',
                'elder',
                'non_member'
            ])->default('stranger');
            
            // Activity tracking in context
            $table->timestamp('joined_context_at')->nullable();
            $table->timestamp('last_activity_in_context')->nullable();
            $table->integer('days_active_in_context')->default(0);
            
            // Context-specific statistics
            $table->integer('interactions_count')->default(0);
            $table->integer('vouches_received_count')->default(0);
            $table->integer('vouches_given_count')->default(0);
            $table->integer('breaches_count')->default(0);
            $table->integer('betrayals_count')->default(0);
            $table->integer('reconciliations_received_count')->default(0);
            
            // Trust metrics in context
            $table->decimal('trust_level', 5, 4)->default(0.0); // 0.0 to 1.0
            $table->decimal('reliability_score', 5, 4)->default(0.0); // Based on promise keeping
            $table->decimal('integrity_score', 5, 4)->default(0.0); // Based on honest behavior
            
            // Unique constraint to ensure one reputation record per user per context
            $table->unique(['user_id', 'context_type', 'context_id'], 'user_context_unique');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['context_type', 'context_id']);
            $table->index('reputation_score');
            $table->index('role_in_context');
            $table->index('trust_level');
            $table->index('last_activity_in_context');
            
            // Composite indexes for common queries
            $table->index(['context_type', 'context_id', 'reputation_score']);
            $table->index(['user_id', 'context_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contextual_reputations');
    }
};
