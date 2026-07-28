<?php

namespace App\Http\Requests\ProjectRequests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class storeProjectRequests extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpg,jpeg,webp,png|max:2048',
            'facebook_link' => 'required|url',
            'drive_link' => 'required|url'
        ];
    }
    #[Override]
    public function messages():array
    {
        return [
            'image.required'=>'Image Required',
            'image.image'=>'Image Required',
            'image.max'=>'Max Character s 2048',
            'image.mimes'=>'The Image Mus Be A File Type (jpg,jpeg,webp,png)',
            'facebook_link.required'=>"Facebook Link Required",
            'facebook_link.url'=>"You Must Enter A Valid Web Link",
            'drive_link.required'=>"Drive Link Link Required",
            'drive_link.url'=>"You Must Enter A Valid Web Link",
        ];
    }
}

