<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'ada@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'ada@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'ada@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    /**
     * @dataProvider invalidLoginProvider
     */
    public function test_login_validates_input(array $payload, string $invalidField): void
    {
        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors($invalidField);
    }

    public static function invalidLoginProvider(): array
    {
        return [
            'missing email'    => [['password' => 'password123'], 'email'],
            'malformed email'  => [['email' => 'not-an-email', 'password' => 'password123'], 'email'],
            'missing password' => [['email' => 'ada@example.com'], 'password'],
        ];
    }

    public function test_a_logged_in_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email)
            ->assertJsonMissingPath('password');
    }

    public function test_guests_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
