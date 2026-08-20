<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single lead payload from n8n or direct API call.
 * Accepts both n8n-style field names ("Full Name") and snake_case ("full_name").
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            // Accept either n8n-style or snake_case keys
            'Full Name'              => 'required_without:full_name|string|max:255',
            'full_name'              => 'required_without:Full Name|string|max:255',

            'Job Title'              => 'nullable|string|max:255',
            'job_title'              => 'nullable|string|max:255',

            'Title Tier'             => 'nullable|string|max:50',
            'title_tier'             => 'nullable|string|max:50',

            'Corporate Work Email'   => 'required_without:corporate_email|email|max:255',
            'corporate_email'        => 'required_without:Corporate Work Email|email|max:255',

            'Email Status'           => 'nullable|string|max:100',
            'email_status'           => 'nullable|string|max:100',

            'Company Name'           => 'required_without:company_name|string|max:255',
            'company_name'           => 'required_without:Company Name|string|max:255',

            'Clean Root Domain'      => 'nullable|string|max:255',
            'clean_root_domain'      => 'nullable|string|max:255',

            'Website Status'         => 'nullable|string|max:100',
            'website_status'         => 'nullable|string|max:100',

            'Executive LinkedIn URL' => 'nullable|string|max:500',
            'executive_linkedin_url' => 'nullable|string|max:500',

            'Company LinkedIn Page'  => 'nullable|string|max:500',
            'company_linkedin_page'  => 'nullable|string|max:500',

            'Industry Classification'=> 'nullable|string|max:255',
            'industry_classification'=> 'nullable|string|max:255',

            'Employee Headcount'     => 'nullable',
            'employee_headcount'     => 'nullable|integer|min:0',

            'HQ Location'            => 'nullable|string|max:500',
            'hq_location'            => 'nullable|string|max:500',

            'country'                => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'Full Name.required_without'            => 'Full name is required.',
            'full_name.required_without'             => 'Full name is required.',
            'Corporate Work Email.required_without'  => 'Corporate email is required.',
            'corporate_email.required_without'       => 'Corporate email is required.',
            'Corporate Work Email.email'             => 'Corporate email must be a valid email address.',
            'corporate_email.email'                  => 'Corporate email must be a valid email address.',
            'Company Name.required_without'          => 'Company name is required.',
            'company_name.required_without'          => 'Company name is required.',
        ];
    }
}
