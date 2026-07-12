<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\CommentLike;
use App\Models\Post;
use App\Models\PostLike;

class PostController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $likedByMe = fn ($q) => $q->addSelect(['liked_by_me' => CommentLike::query()
            ->selectRaw('1')->whereColumn('comment_id', 'comments.id')
            ->where('user_id', $userId)->limit(1)]);

        $posts = Post::query()
            ->visibleTo(auth()->user())
            ->with([
                'user:id,first_name,last_name',
                'attachments',
                'comments' => fn ($q) => $q->whereNull('parent_id')
                    // preview: only the latest 2 top-level comments per post
                    ->whereRaw('(select count(*) from comments c2 where c2.post_id = comments.post_id and c2.parent_id is null and c2.id > comments.id) < 2')
                    ->with('user:id,first_name,last_name')
                    ->withCount(['replies', 'likes'])
                    ->tap($likedByMe)
                    ->with(['replies' => fn ($q) => $q
                        // preview: only the latest 2 replies per comment
                        ->whereRaw('(select count(*) from comments c2 where c2.parent_id = comments.parent_id and c2.id > comments.id) < 2')
                        ->with('user:id,first_name,last_name')
                        ->withCount('likes')->tap($likedByMe)->oldest()])
                    ->latest()->latest('id'),
            ])
            ->withCount(['likes', 'comments'])
            ->addSelect(['liked_by_me' => PostLike::query()
                ->selectRaw('1')->whereColumn('post_id', 'posts.id')
                ->where('user_id', $userId)->limit(1)])
            ->latest()
            ->latest('id')
            ->cursorPaginate(20);

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
