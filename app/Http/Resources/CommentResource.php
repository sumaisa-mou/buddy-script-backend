<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'parent_id'  => $this->parent_id,
            'created_at' => $this->created_at,
            'author'     => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ],
            'replies_count' => $this->whenCounted('replies'),
            'replies'       => CommentResource::collection($this->whenLoaded('replies')),
            // likes go here later: 'likes_count', 'liked_by_me'
        ];
    }
}
