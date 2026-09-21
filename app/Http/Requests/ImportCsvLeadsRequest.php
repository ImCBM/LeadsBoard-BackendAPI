<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A CSV file is required for import.',
            'file.file'     => 'The uploaded file is invalid.',
            'file.mimes'    => 'The file must be a valid CSV file (.csv or .txt).',
            'file.max'      => 'The CSV file size may not exceed 10MB.',
        ];
    }
}
