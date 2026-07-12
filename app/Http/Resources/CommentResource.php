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
            'likes_count'   => $this->whenCounted('likes'),
            'liked_by_me'   => (bool) $this->liked_by_me,
        ];
    }
}
