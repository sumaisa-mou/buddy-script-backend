<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'visibility' => $this->visibility,
            'created_at' => $this->created_at,
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'images' => $this->attachments->pluck('url'),
            'likes_count' => (int) $this->likes_count,
            'liked_by_me' => (bool) $this->liked_by_me,
            'comments_count' => (int)$this->comments_count,
        ];
    }
}
