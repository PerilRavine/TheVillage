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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable for Passkey-only users
            $table->string('display_name');
            
            // Passkey/FIDO2 authentication fields
            $table->json('passkey_credentials')->nullable(); // Store WebAuthn credentials
            $table->boolean('passkey_enabled')->default(false);
            
            // Reputation and role system
            $table->enum('role', [
                'stranger',
                'sojourner', 
                'denizen',
                'steward',
                'elder',
                'reeve',
                'high_reeve'
            ])->default('stranger');
            
            // Base integrity score (0-100)
            $table->decimal('base_integrity', 8, 4)->default(0.0);
            $table->decimal('available_integrity', 8, 4)->default(0.0); // Integrity not locked in stakes
            $table->decimal('locked_integrity', 8, 4)->default(0.0); // Integrity currently staked
            
            // Identity verification
            $table->boolean('identity_verified')->default(false);
            $table->string('verification_method')->nullable(); // 'passkey', 'government_id', etc.
            $table->timestamp('verified_at')->nullable();
            
            // Activity tracking for time-based erosion
            $table->timestamp('last_activity_at')->nullable();
            $table->decimal('inertia_coefficient', 5, 4)->default(1.0); // Calculated from activity patterns
            
            // Profile metadata
            $table->text('bio')->nullable();
            $table->json('profile_data')->nullable(); // Flexible profile fields
            
            // Privacy settings
            $table->json('privacy_settings')->nullable(); // Privacy preferences per context
            $table->boolean('public_profile')->default(false);
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('role');
            $table->index('base_integrity');
            $table->index('available_integrity');
            $table->index('last_activity_at');
            $table->index('identity_verified');
            
            // Full-text search index
            $table->fullText(['username', 'display_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
