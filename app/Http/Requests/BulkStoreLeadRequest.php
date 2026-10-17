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
            'leads'   => 'required|array|min:1|max:500',
            'leads.*' => 'required|array',

            // Each lead must have at least a name
            // Email and company are optional — real-world scraped data is often incomplete
            'leads.*.Full Name'            => 'required_without:leads.*.full_name|string|max:255',
            'leads.*.full_name'            => 'required_without:leads.*.Full Name|string|max:255',
            'leads.*.Corporate Work Email' => 'nullable|email|max:255',
            'leads.*.corporate_email'      => 'nullable|email|max:255',
            'leads.*.Company Name'         => 'nullable|string|max:255',
            'leads.*.company_name'         => 'nullable|string|max:255',
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
