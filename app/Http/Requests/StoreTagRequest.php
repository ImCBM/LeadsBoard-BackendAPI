<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100|unique:tags,name',
            'type'        => 'sometimes|string|in:public,system',
            'color'       => ['sometimes', 'string', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'description' => 'sometimes|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tag name is required.',
            'name.unique'   => 'A tag with this name already exists.',
            'color.regex'   => 'Color must be a valid hex color code (e.g. #ef4444).',
        ];
    }
}
