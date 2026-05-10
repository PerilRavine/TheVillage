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
        Schema::create('domiciles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('theme')->default('default');
            $table->json('privacy_settings')->nullable();
            $table->foreignId('encryption_key_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('storage_quota')->default(1073741824); // 1GB in bytes
            $table->integer('storage_used')->default(0);
            $table->boolean('is_public')->default(false);
            $table->string('access_code')->nullable();
            $table->boolean('brand_tracking_enabled')->default(true);
            $table->boolean('analytics_enabled')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('is_public');
            $table->index('brand_tracking_enabled');
            $table->index('analytics_enabled');
            $table->index(['user_id', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domiciles');
    }
};
