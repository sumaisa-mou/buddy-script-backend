<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostLike;

class PostController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $posts = Post::query()
            ->visibleTo(auth()->user())
            ->with(['user:id,first_name,last_name', 'attachments'])
            ->withCount('likes')
            ->withCount(['likes', 'comments'])
            ->addSelect(['liked_by_me' => PostLike::query()
                ->selectRaw('1')->whereColumn('post_id', 'posts.id')
                ->where('user_id', $userId)->limit(1)])
            ->latest()
            ->latest('id')
            ->cursorPaginate(15);

        return PostResource::collection($posts);
    }
    public function store(PostRequest $request)
    {
        $post = $request->user()->posts()->create(
            $request->safe()->only(['body', 'visibility'])
        );

        $failed = 0;
        foreach ($request->file('images', []) as $image) {
            try {
                $path = $image->store('posts', 'public');
                $post->attachments()->create(['path' => $path]);
            } catch (\Throwable $e) {
                report($e);   // log & continue — post already saved
                $failed++;
            }
        }

        return (new PostResource($post->load('user', 'attachments')->loadCount('likes')
            ->loadCount(['likes', 'comments'])))
            ->additional(['message' => $failed
                ? "Post created, but {$failed} image(s) failed to upload."
                : 'Post created.'])
            ->response()
            ->setStatusCode(201);
    }
}
