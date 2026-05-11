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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('ip_address')->nullable();
            $table->integer('port')->default(8080);
            $table->integer('max_population')->default(1000);
            $table->text('description')->nullable();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('status')->default('active');
            $table->string('version')->default('1.0.0');
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
