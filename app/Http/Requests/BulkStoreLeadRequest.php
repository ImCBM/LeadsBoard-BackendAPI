<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a bulk lead import payload (array of leads).
 */
class BulkStoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'leads' => 'required|array|min:1|max:500',
            'leads.*' => 'required|array',
        ];
    }

    public function messages(): array
    {
        return [
            'leads.required' => 'The leads array is required.',
            'leads.array'    => 'Leads must be provided as an array.',
            'leads.min'      => 'At least one lead is required.',
            'leads.max'      => 'Maximum 500 leads per batch request.',
        ];
    }
}
