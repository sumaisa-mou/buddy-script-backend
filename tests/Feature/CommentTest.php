<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function createPost(User $author): Post
    {
        return $author->posts()->create(['body' => 'A post', 'visibility' => 'public']);
    }

    public function test_a_user_can_comment_on_a_post(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);

        $response = $this->actingAs($user)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Nice post!']);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Nice post!');

        $this->assertDatabaseHas('comments', [
            'post_id'   => $post->id,
            'user_id'   => $user->id,
            'body'      => 'Nice post!',
            'parent_id' => null,
        ]);
    }

    public function test_a_user_can_reply_to_a_comment(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);
        $comment = $user->comments()->create(['post_id' => $post->id, 'body' => 'Top level']);

        $response = $this->actingAs($user)->postJson("/api/posts/{$post->id}/comments", [
            'body'      => 'A reply',
            'parent_id' => $comment->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.parent_id', $comment->id);
    }

    public function test_a_user_can_fetch_a_posts_comments(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);
        $user->comments()->create(['post_id' => $post->id, 'body' => 'First comment']);
        $user->comments()->create(['post_id' => $post->id, 'body' => 'Second comment']);

        $response = $this->actingAs($user)->getJson("/api/posts/{$post->id}/comments");

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}
