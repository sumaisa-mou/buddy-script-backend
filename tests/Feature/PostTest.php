<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/post', [
            'body'       => 'My first post',
            'visibility' => 'public',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'My first post')
            ->assertJsonPath('data.visibility', 'public');

        $this->assertDatabaseHas('posts', [
            'user_id'    => $user->id,
            'body'       => 'My first post',
            'visibility' => 'public',
        ]);
    }

    public function test_a_user_can_fetch_the_feed(): void
    {
        $user = User::factory()->create();
        $user->posts()->create(['body' => 'Older post', 'visibility' => 'public']);
        $user->posts()->create(['body' => 'Newer post', 'visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Newer post')   // newest first
            ->assertJsonPath('data.1.body', 'Older post');
    }

    public function test_guests_cannot_access_posts(): void
    {
        $this->postJson('/api/post', ['body' => 'x', 'visibility' => 'public'])
            ->assertUnauthorized();

        $this->getJson('/api/posts')->assertUnauthorized();
    }
}
