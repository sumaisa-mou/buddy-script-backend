<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Comment;

class CommentLikeController extends Controller
{
    public function store(Comment $comment)
    {
        abort_unless($comment->post->isVisibleTo(auth()->user()), 404);

        $comment->likes()->firstOrCreate(['user_id' => auth()->id()]);

        return response()->json([
            'liked'       => true,
            'likes_count' => $comment->likes()->count(),
        ]);
    }

    public function destroy(Comment $comment)
    {
        abort_unless($comment->post->isVisibleTo(auth()->user()), 404);

        $comment->likes()->where('user_id', auth()->id())->delete();

        return response()->json([
            'liked'       => false,
            'likes_count' => $comment->likes()->count(),
        ]);
    }

    public function index(Comment $comment)
    {
        abort_unless($comment->post->isVisibleTo(auth()->user()), 404);

        return UserResource::collection(
            $comment->likers()->select('users.id', 'first_name', 'last_name')->cursorPaginate(20)
        );
    }
}
