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
        Schema::create('user_villages', function (Blueprint $table) {
            $table->id();
            
            // User and village relationship
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('village_id')->constrained()->onDelete('cascade');
            
            // User's role in this village
            $table->enum('role', [
                'stranger',     // Can view public areas only
                'sojourner',   // Can participate with limitations
                'denizen',     // Full village member
                'steward',     // Moderator role
                'elder'        // Village leadership
            ])->default('stranger');
            
            // Membership status
            $table->enum('status', [
                'invited',     // Received invitation
                'joined',      // Active member
                'suspended',   // Temporarily suspended
                'left',        // Left voluntarily
                'expelled'     // Removed by village
            ])->default('joined');
            
            // Membership timeline
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('role_changed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('left_at')->nullable();
            
            // Vouching information (how user joined)
            $table->foreignId('vouched_by')->nullable()->constrained('users')->onDelete('set null'); // Who vouched for them
            $table->foreignId('vouch_id')->nullable()->constrained()->onDelete('set null'); // The vouch that got them in
            $table->text('join_reason')->nullable(); // Why they wanted to join
            
            // Activity metrics in village
            $table->integer('interactions_count')->default(0);
            $table->integer('resources_shared_count')->default(0);
            $table->integer('moderation_actions_count')->default(0); // For stewards/elders
            $table->decimal('contribution_score', 8, 4)->default(0.0); // Overall contribution to village
            
            // Permissions and privileges
            $table->json('permissions')->nullable(); // Additional permissions beyond role
            $table->boolean('can_invite_others')->default(false);
            $table->boolean('can_moderate')->default(false);
            $table->boolean('can_manage_resources')->default(false);
            
            // Privacy settings within village
            $table->json('privacy_settings')->nullable(); // Village-specific privacy
            $table->boolean('profile_visible_to_village')->default(true);
            $table->boolean('activity_visible_to_village')->default(true);
            
            // Unique constraint to ensure one membership per user per village
            $table->unique(['user_id', 'village_id'], 'user_village_unique');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['village_id', 'role']);
            $table->index(['village_id', 'status']);
            $table->index(['user_id', 'role']);
            $table->index('vouched_by');
            $table->index('joined_at');
            $table->index('last_activity_at');
            
            // Composite indexes for common queries
            $table->index(['village_id', 'status', 'role']); // For getting active members by role
            $table->index(['user_id', 'status']); // For user's active villages
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_villages');
    }
};
