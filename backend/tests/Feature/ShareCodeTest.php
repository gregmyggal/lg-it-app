<?php

namespace Tests\Feature;

use App\Models\Cours;
use App\Models\ShareCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_content_with_valid_share_code(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);
        $code = ShareCode::create([
            'code' => ShareCode::generateCode(),
            'shareable' => $cours,
        ]);

        $response = $this->getJson("/api/share/{$code->code}", [
            'X-Share-Code' => $code->code,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'type' => 'Cours',
            'content' => ['id' => $cours->id],
        ]);
    }

    public function test_cannot_access_with_invalid_code(): void
    {
        $response = $this->getJson('/api/share/INVALID123456', [
            'X-Share-Code' => 'INVALID123456',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Invalid share code']);
    }

    public function test_cannot_access_expired_code(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);
        $code = ShareCode::create([
            'code' => ShareCode::generateCode(),
            'shareable' => $cours,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/share/{$code->code}", [
            'X-Share-Code' => $code->code,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_access_code_with_reached_max_uses(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);
        $code = ShareCode::create([
            'code' => ShareCode::generateCode(),
            'shareable' => $cours,
            'max_uses' => 2,
            'used_count' => 2,
        ]);

        $response = $this->getJson("/api/share/{$code->code}", [
            'X-Share-Code' => $code->code,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_access_unpublished_content(): void
    {
        $cours = Cours::factory()->create(['statut' => 'draft']);
        $code = ShareCode::create([
            'code' => ShareCode::generateCode(),
            'shareable' => $cours,
        ]);

        $response = $this->getJson("/api/share/{$code->code}", [
            'X-Share-Code' => $code->code,
        ]);

        $response->assertStatus(404);
    }

    public function test_used_count_increments_on_access(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);
        $code = ShareCode::create([
            'code' => ShareCode::generateCode(),
            'shareable' => $cours,
            'used_count' => 0,
        ]);

        $this->getJson("/api/share/{$code->code}", [
            'X-Share-Code' => $code->code,
        ]);

        $code->refresh();
        $this->assertEquals(1, $code->used_count);
    }

    public function test_share_code_is_unique(): void
    {
        $cours1 = Cours::factory()->create();
        $cours2 = Cours::factory()->create();

        $code = ShareCode::generateCode();

        ShareCode::create([
            'code' => $code,
            'shareable' => $cours1,
        ]);

        // generateCode() should not return same code twice (statistically)
        $newCode = ShareCode::generateCode();
        $this->assertNotEquals($code, $newCode);
    }
}
