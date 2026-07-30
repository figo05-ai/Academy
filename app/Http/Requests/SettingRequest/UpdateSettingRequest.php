<?php

namespace App\Http\Requests\SettingRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class UpdateSettingRequest extends FormRequest
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
            'master_drive_link' => 'nullable|url',
            'sketchup_link' => 'required|url',
            'max_link' => 'required|url',
            'autocad_link' => 'required|url',
            'manual_link' => 'required|url',
            'landscape_link' => 'required|url',
        ];
    }

    #[Override]
    public function messages() :array{
        return [
            'sketchup_link.required'=>'Sketchup Link required ',
            'sketchup_link.url'=>'You Must Enter A Valid Web Link',
            'max_link.required'=>'Max Link required ',
            'max_link.url'=>'You Must Enter A Valid Web Link',
            'autocad_link.required'=>'Autocad Link required ',
            'autocad_link.url'=>'You Must Enter A Valid Web Link',
            'manual_link.required'=>'Manual Link required ',
            'manual_link.url'=>'You Must Enter A Valid Web Link',
            'landscape_link.required'=>'Landscape Link required ',
            'landscape_link.url'=>'You Must Enter A Valid Web Link',
        ];

    }
}
