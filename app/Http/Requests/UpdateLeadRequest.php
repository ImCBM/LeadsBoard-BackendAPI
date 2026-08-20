<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates updates to an existing lead from the dashboard or API.
 */
class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        $leadId = $this->route('lead');

        return [
            'full_name'              => 'sometimes|string|max:255',
            'job_title'              => 'nullable|string|max:255',
            'title_tier'             => 'nullable|string|in:' . implode(',', Lead::TITLE_TIERS),
            'corporate_email'        => "sometimes|email|max:255|unique:leads,corporate_email,{$leadId}",
            'email_status'           => 'nullable|string|max:100',
            'company_name'           => 'sometimes|string|max:255',
            'clean_root_domain'      => 'nullable|string|max:255',
            'website_status'         => 'nullable|string|max:100',
            'executive_linkedin_url' => 'nullable|url|max:500',
            'company_linkedin_page'  => 'nullable|url|max:500',
            'industry_classification'=> 'nullable|string|max:255',
            'employee_headcount'     => 'nullable|integer|min:0',
            'hq_location'            => 'nullable|string|max:500',
            'country'                => 'nullable|string|max:255',
            'status'                 => 'nullable|string|in:' . implode(',', Lead::STATUSES),
            'notes'                  => 'nullable|string|max:5000',
        ];
    }
}
