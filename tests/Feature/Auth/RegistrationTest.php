<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'            => 'Ada',
            'last_name'             => 'Lovelace',
            'email'                 => 'ada@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_a_user_can_register_with_valid_details(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('message', 'Registration successful.')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.first_name', 'Ada');

        $this->assertDatabaseHas('users', [
            'email'      => 'ada@example.com',
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
        ]);
    }

    public function test_the_password_is_hashed_and_never_returned(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload());

        // Raw password must never appear in the response body.
        $response->assertJsonMissingPath('user.password');

        $user = User::firstWhere('email', 'ada@example.com');
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_logs_the_user_in(): void
    {
        $this->postJson('/api/register', $this->validPayload());

        $this->assertAuthenticated();
    }

    /**
     * @dataProvider invalidRegistrationProvider
     */
    public function test_registration_rejects_invalid_input(array $overrides, string $invalidField): void
    {
        $response = $this->postJson('/api/register', $this->validPayload($overrides));

        $response->assertStatus(422)->assertJsonValidationErrors($invalidField);
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidRegistrationProvider(): array
    {
        return [
            'missing first name'      => [['first_name' => ''], 'first_name'],
            'missing last name'       => [['last_name' => ''], 'last_name'],
            'malformed email'         => [['email' => 'not-an-email'], 'email'],
            'password too short'      => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'password not confirmed'  => [['password_confirmation' => 'different123'], 'password'],
        ];
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }
}
