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
        Schema::create('vouches', function (Blueprint $table) {
            $table->id();
            
            // Vouch participants
            $table->foreignId('voucher_id')->constrained('users')->onDelete('cascade'); // Who is vouching
            $table->foreignId('vouchee_id')->constrained('users')->onDelete('cascade'); // Who is being vouched for
            
            // Vouch context
            $table->enum('context_type', ['village', 'map', 'global'])->default('village');
            $table->unsignedBigInteger('context_id')->nullable(); // village_id or null for map/global
            
            // Staking mechanics
            $table->decimal('stake_amount', 8, 4); // Amount of integrity staked
            $table->decimal('bonus_amount', 8, 4)->default(0.0); // 20% bonus if successful
            $table->decimal('forfeit_amount', 8, 4)->default(0.0); // Amount forfeited if failed
            
            // Vouch status and lifecycle
            $table->enum('status', [
                'pending',      // Vouch created, waiting for vouchee to achieve denizen
                'successful',   // Vouchee achieved denizen status, stake returned + bonus
                'failed',       // Vouchee failed to achieve denizen, stake forfeited
                'expired',      // Vouch expired without resolution
                'cancelled'     // Vouch cancelled by voucher
            ])->default('pending');
            
            // Vouch conditions and goals
            $table->json('conditions')->nullable(); // Specific conditions for vouch success
            $table->enum('target_role', ['sojourner', 'denizen'])->default('denizen'); // Role vouchee must achieve
            $table->decimal('target_reputation', 8, 4)->nullable(); // Minimum reputation required
            
            // Timestamps for vouch lifecycle
            $table->timestamp('locked_at'); // When stake was locked
            $table->timestamp('expires_at'); // When vouch expires (default 30 days)
            $table->timestamp('resolved_at')->nullable(); // When vouch was resolved
            $table->timestamp('target_achieved_at')->nullable(); // When vouchee achieved target
            
            // Resolution details
            $table->text('resolution_reason')->nullable(); // Why vouch succeeded/failed
            $table->json('resolution_metadata')->nullable(); // Additional resolution data
            
            // Audit trail
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('signature_proof')->nullable(); // Cryptographic proof of vouch creation
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['voucher_id', 'status']);
            $table->index(['vouchee_id', 'status']);
            $table->index(['context_type', 'context_id']);
            $table->index('status');
            $table->index('expires_at');
            $table->index('stake_amount');
            
            // Composite indexes for common queries
            $table->index(['voucher_id', 'vouchee_id']);
            $table->index(['vouchee_id', 'context_type', 'context_id']);
            $table->index(['status', 'expires_at']); // For finding expired vouches
            
            // Prevent duplicate active vouches
            $table->unique(['voucher_id', 'vouchee_id', 'context_type', 'context_id'], 'active_vouch_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouches');
    }
};
