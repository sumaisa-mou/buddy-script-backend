<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // route is already behind auth:sanctum
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body'       => 'required|string|max:5000',
            'visibility' => 'required|in:public,private',
            'images'     => 'nullable|array|max:4',
            'images.*'   => 'image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'images.max'     => 'You can attach up to 4 images.',
            'images.*.image' => 'One of the files is not a valid image.',
            'images.*.mimes' => 'Images must be JPG, PNG, WEBP, or GIF.',
            'images.*.max'   => 'Each image must be under 5 MB.',
        ];
    }
}
