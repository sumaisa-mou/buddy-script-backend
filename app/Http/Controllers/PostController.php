<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::query()
            ->visibleTo(auth()->user())
            ->with(['user:id,first_name,last_name', 'attachments'])
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

        return (new PostResource($post->load('user', 'attachments')))
            ->additional(['message' => $failed
                ? "Post created, but {$failed} image(s) failed to upload."
                : 'Post created.'])
            ->response()
            ->setStatusCode(201);
    }
}
