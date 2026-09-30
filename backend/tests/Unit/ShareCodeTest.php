<?php

namespace Tests\Unit;

use App\Models\Cours;
use App\Models\ShareCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_code_model_exists(): void
    {
        $this->assertTrue(class_exists(ShareCode::class));
    }

    public function test_generate_code_returns_12_char_string(): void
    {
        $code = ShareCode::generateCode();

        $this->assertIsString($code);
        $this->assertEquals(12, strlen($code));
    }

    public function test_generate_code_is_uppercase(): void
    {
        $code = ShareCode::generateCode();

        $this->assertEquals($code, strtoupper($code));
    }

    public function test_share_code_is_unique(): void
    {
        $code1 = ShareCode::generateCode();
        $code2 = ShareCode::generateCode();

        $this->assertNotEquals($code1, $code2);
    }

    public function test_can_check_code_validity(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);

        $validCode = ShareCode::forceCreate([
            'code' => ShareCode::generateCode(),
            'shareable_type' => Cours::class,
            'shareable_id' => $cours->id,
        ]);

        $this->assertTrue($validCode->isValid());
    }

    public function test_expired_code_is_invalid(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);

        $expiredCode = ShareCode::forceCreate([
            'code' => ShareCode::generateCode(),
            'shareable_type' => Cours::class,
            'shareable_id' => $cours->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($expiredCode->isValid());
    }

    public function test_code_with_max_uses_reached_is_invalid(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);

        $code = ShareCode::forceCreate([
            'code' => ShareCode::generateCode(),
            'shareable_type' => Cours::class,
            'shareable_id' => $cours->id,
            'max_uses' => 5,
            'used_count' => 5,
        ]);

        $this->assertFalse($code->isValid());
    }

    public function test_code_with_usage_below_limit_is_valid(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);

        $code = ShareCode::forceCreate([
            'code' => ShareCode::generateCode(),
            'shareable_type' => Cours::class,
            'shareable_id' => $cours->id,
            'max_uses' => 10,
            'used_count' => 3,
        ]);

        $this->assertTrue($code->isValid());
    }

    public function test_increment_usage(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);

        $code = ShareCode::forceCreate([
            'code' => ShareCode::generateCode(),
            'shareable_type' => Cours::class,
            'shareable_id' => $cours->id,
            'used_count' => 0,
        ]);

        $code->incrementUsage();

        $this->assertEquals(1, $code->fresh()->used_count);
    }

    public function test_share_code_has_polymorphic_relation(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);

        $code = ShareCode::forceCreate([
            'code' => ShareCode::generateCode(),
            'shareable_type' => Cours::class,
            'shareable_id' => $cours->id,
        ]);

        $this->assertNotNull($code->shareable);
        $this->assertInstanceOf(Cours::class, $code->shareable);
        $this->assertEquals($cours->id, $code->shareable->id);
    }
}
