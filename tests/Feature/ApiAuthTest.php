<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Token issuance ───────────────────────────────────────────────────────

    public function test_user_can_get_api_token(): void
    {
        User::factory()->create([
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email'       => 'test@example.com',
            'password'    => 'password',
            'device_name' => 'Apple Watch',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token']);
    }

    public function test_token_issuance_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email'       => 'test@example.com',
            'password'    => 'wrong-password',
            'device_name' => 'Apple Watch',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_token_issuance_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/token', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_token_issuance_fails_for_nonexistent_user(): void
    {
        $response = $this->postJson('/api/auth/token', [
            'email'       => 'nobody@example.com',
            'password'    => 'password',
            'device_name' => 'Apple Watch',
        ]);

        $response->assertUnprocessable();
    }

    // ── Token revocation ─────────────────────────────────────────────────────

    public function test_user_can_revoke_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('Apple Watch')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->deleteJson('/api/auth/token');

        $response->assertOk();
        $response->assertJson(['message' => 'Token revoked']);

        // Token should be deleted from the database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_revoke_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/auth/token');

        $response->assertUnauthorized();
    }
}
