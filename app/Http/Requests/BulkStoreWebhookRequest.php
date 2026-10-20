<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkStoreWebhookRequest extends FormRequest
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
            'leads' => 'required|array|min:1|max:500',
            'leads.*' => 'required|array',

            'leads.*.Full Name' => 'required_without:leads.*.full_name|string|max:255',
            'leads.*.full_name' => 'required_without:leads.*.Full Name|string|max:255',

            'leads.*.Corporate Work Email' => 'required_without:leads.*.corporate_email|email|max:255',
            'leads.*.corporate_email' => 'required_without:leads.*.Corporate Work Email|email|max:255',

            'leads.*.Company Name' => 'required_without:leads.*.company_name|string|max:255',
            'leads.*.company_name' => 'required_without:leads.*.Company Name|string|max:255',
        ];
    }
}
