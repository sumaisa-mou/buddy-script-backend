<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\CommentLike;
use App\Models\Post;

class CommentController extends Controller
{
    public function index(Post $post)
    {
        $this->authorizeView($post);

        $userId = auth()->id();
        $likedByMe = fn ($q) => $q->addSelect(['liked_by_me' => CommentLike::query()
            ->selectRaw('1')->whereColumn('comment_id', 'comments.id')
            ->where('user_id', $userId)->limit(1)]);

        $comments = $post->comments()
            ->whereNull('parent_id')                       // top-level only
            ->with('user:id,first_name,last_name')
            ->withCount(['replies', 'likes'])
            ->tap($likedByMe)
            ->with(['replies' => fn ($q) => $q
                ->with('user:id,first_name,last_name')
                ->withCount('likes')
                ->tap($likedByMe)
                ->oldest()])                               // replies chronological
            ->latest()->latest('id')
            ->cursorPaginate(10);

        return CommentResource::collection($comments);
    }

    public function store(CommentRequest $request, Post $post)
    {
        $this->authorizeView($post);

        $comment = $request->user()->comments()->create([
            'post_id'   => $post->id,
            'body'      => $request->validated('body'),
            'parent_id' => $request->validated('parent_id'),
        ]);

        return new CommentResource($comment->load('user'));
    }

    // A user can only comment on / read comments of a post they can see.
    private function authorizeView(Post $post): void
    {
        abort_unless(
            $post->visibility === Post::VISIBILITY_PUBLIC || $post->user_id === auth()->id(),
            404   // 404, not 403 — don't reveal a private post exists
        );
    }
}
