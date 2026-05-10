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
        Schema::create('domicile_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domicile_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('content_type');
            $table->longText('content_data')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('file_size')->default(0);
            $table->string('encryption_status')->default('unencrypted');
            $table->string('access_level')->default('private');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_public')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('download_count')->default(0);
            $table->decimal('reputation_score', 8, 4)->default(0.0000);
            $table->timestamps();
            
            // Indexes
            $table->index('domicile_id');
            $table->index('user_id');
            $table->index('content_type');
            $table->index('access_level');
            $table->index('is_public');
            $table->index(['domicile_id', 'user_id']);
            $table->index(['content_type', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domicile_contents');
    }
};
