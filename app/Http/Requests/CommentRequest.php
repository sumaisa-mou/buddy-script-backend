<?php

namespace App\Http\Requests;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // behind auth:sanctum
    }

    public function rules(): array
    {
        return [
            'body' => 'required|string|max:2000',
            // reply target must exist AND belong to this same post
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('comments', 'id')->where('post_id', $this->route('post')->id),
            ],
        ];
    }

    // Enforce 2-level nesting: you can't reply to a reply.
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $parentId = $this->input('parent_id');
            if ($parentId && Comment::whereKey($parentId)->value('parent_id')) {
                $v->errors()->add('parent_id', 'You can only reply to a top-level comment.');
            }
        });
    }
}
