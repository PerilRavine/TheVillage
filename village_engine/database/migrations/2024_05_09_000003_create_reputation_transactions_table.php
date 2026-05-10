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
        Schema::create('reputation_transactions', function (Blueprint $table) {
            $table->id();
            
            // User involved in the transaction
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Transaction type and context
            $table->enum('transaction_type', [
                'integrity_change',    // Base integrity modifications
                'contextual_reputation', // Context-specific reputation
                'stake_lock',         // Locking integrity for vouching
                'stake_release',      // Releasing locked integrity
                'stake_forfeit',      // Forfeiting failed stake
                'vouch_created',      // Creating a vouch
                'vouch_resolved',     // Resolving a vouch (success/failure)
                'breach_committed',   // Breaking a promise
                'betrayal_committed', // Betraying trust
                'reconciliation',     // Being forgiven
                'role_change',        // Role progression/demotion
                'time_erosion',       // Time-based integrity decay
                'activity_bonus',     // Bonus for activity
                'verification_bonus'  // Bonus for identity verification
            ]);
            
            // Context for contextual reputation
            $table->enum('context_type', ['village', 'map', 'global'])->default('global');
            $table->unsignedBigInteger('context_id')->nullable(); // village_id or null for map/global
            
            // Transaction amounts and values
            $table->decimal('amount', 8, 4)->default(0.0); // Positive or negative change
            $table->decimal('balance_before', 8, 4); // Balance before transaction
            $table->decimal('balance_after', 8, 4); // Balance after transaction
            
            // Related entities for complex transactions
            $table->foreignId('related_user_id')->nullable()->constrained('users')->onDelete('set null'); // For vouches, breaches, etc.
            $table->foreignId('village_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('vouch_id')->nullable();
            
            // Transaction metadata
            $table->string('reason')->nullable(); // Human-readable reason
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->text('notes')->nullable(); // Admin notes or detailed explanations
            
            // Transaction processing
            $table->enum('status', ['pending', 'processed', 'failed', 'reversed'])->default('processed');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null'); // Admin who processed
            
            // Audit trail
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('signature_proof')->nullable(); // Cryptographic proof for sensitive operations
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'transaction_type']);
            $table->index(['context_type', 'context_id']);
            $table->index(['transaction_type', 'status']);
            $table->index('amount');
            $table->index('created_at');
            $table->index(['related_user_id', 'transaction_type']);
            $table->index(['village_id', 'transaction_type']);
            
            // Composite indexes for common queries
            $table->index(['user_id', 'context_type', 'context_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reputation_transactions');
    }
};
