<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Domicile;
use App\Models\DomicileContent;
use App\Models\BrandTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DomicileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test domicile creation.
     */
    public function test_domicile_creation(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->post('/api/domiciles', [
            'name' => 'Test Domicile',
            'description' => 'A test personal space',
            'theme' => 'default',
            'is_public' => false,
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('domiciles', [
            'user_id' => $user->id,
            'name' => 'Test Domicile',
        ]);
    }

    /**
     * Test content upload to domicile.
     */
    public function test_content_upload(): void
    {
        $user = User::factory()->create();
        $domicile = Domicile::factory()->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)->post("/api/domiciles/{$domicile->id}/content", [
            'title' => 'Test Document',
            'content_type' => 'document',
            'content' => 'This is test content',
            'access_level' => 'private',
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('domicile_contents', [
            'domicile_id' => $domicile->id,
            'title' => 'Test Document',
        ]);
    }

    /**
     * Test brand tracking in content.
     */
    public function test_brand_tracking(): void
    {
        $user = User::factory()->create();
        $domicile = Domicile::factory()->create(['user_id' => $user->id]);
        $content = DomicileContent::factory()->create([
            'domicile_id' => $domicile->id,
            'user_id' => $user->id,
            'title' => 'Apple iPhone Review',
            'description' => 'Great product from Apple',
        ]);
        
        $brandTracking = new BrandTracking();
        $brands = $brandTracking->extractBrands($content->title . ' ' . $content->description);
        
        $this->assertContains('Apple', $brands);
    }

    /**
     * Test domicile access control.
     */
    public function test_domicile_access_control(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $domicile = Domicile::factory()->create([
            'user_id' => $owner->id,
            'is_public' => false,
        ]);
        
        // Owner should have access
        $response = $this->actingAs($owner)->get("/api/domiciles/{$domicile->id}");
        $response->assertStatus(200);
        
        // Stranger should not have access
        $response = $this->actingAs($stranger)->get("/api/domiciles/{$domicile->id}");
        $response->assertStatus(403);
    }

    /**
     * Test storage quota enforcement.
     */
    public function test_storage_quota_enforcement(): void
    {
        $user = User::factory()->create();
        $domicile = Domicile::factory()->create([
            'user_id' => $user->id,
            'storage_quota' => 1000, // 1KB
            'storage_used' => 999,
        ]);
        
        $response = $this->actingAs($user)->post("/api/domiciles/{$domicile->id}/content", [
            'title' => 'Large Document',
            'content_type' => 'document',
            'content' => str_repeat('x', 100), // 100 bytes
        ]);
        
        $response->assertStatus(422);
        $response->assertJson(['error' => 'Storage quota exceeded']);
    }
}
