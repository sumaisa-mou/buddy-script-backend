<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Post;

class PostLikeController extends Controller
{
    public function store(Post $post)
    {
        abort_unless($post->isVisibleTo(auth()->user()), 404);

        $post->likes()->firstOrCreate(['user_id' => auth()->id()]);   // idempotent

        return response()->json([
            'liked'       => true,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function destroy(Post $post)
    {
        abort_unless($post->isVisibleTo(auth()->user()), 404);

        $post->likes()->where('user_id', auth()->id())->delete();

        return response()->json([
            'liked'       => false,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function index(Post $post)   // who liked, paginated
    {
        abort_unless($post->isVisibleTo(auth()->user()), 404);

        return UserResource::collection(
            $post->likers()->select('users.id', 'first_name', 'last_name')->cursorPaginate(20)
        );
    }
}
