<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'lead_ids'          => 'sometimes|array|min:1|max:1000',
            'ids'               => 'sometimes|array|min:1|max:1000',
            'id_ranges'         => 'sometimes|array|min:1|max:100',
            'id_ranges.*'       => 'string|max:50',
            'id_from'           => 'sometimes|integer|min:1',
            'id_to'             => 'sometimes|integer|min:1',
            'emails'            => 'sometimes|array|min:1|max:1000',
            'emails.*'          => 'string|max:255',
            'email_domain'      => 'sometimes|string|max:255',
            'email_domains'     => 'sometimes|array|min:1|max:100',
            'email_domains.*'   => 'string|max:255',
            'email_pattern'     => 'sometimes|string|max:255',
            'email_patterns'    => 'sometimes|array|min:1|max:100',
            'email_patterns.*'  => 'string|max:255',
            'tag'               => 'sometimes|string|max:100',
            'tags'              => 'sometimes|array|min:1|max:50',
            'tags.*'            => 'string|max:100',
            'channel'           => 'sometimes|string|max:50',
            'ingestion_channel' => 'sometimes|string|max:50',
            'status'            => 'sometimes|string|in:new,reviewed,qualified,rejected',
            'date_from'         => 'sometimes|date',
            'date_to'           => 'sometimes|date',
            'date_ranges'       => 'sometimes|array|min:1|max:50',
            'confirm'           => 'sometimes|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasTarget = $this->hasAny([
                'lead_ids', 'ids', 'id_ranges', 'id_from', 'id_to',
                'emails', 'email_domain', 'email_domains', 'email_pattern', 'email_patterns',
                'tag', 'tags', 'channel', 'ingestion_channel', 'status',
                'date_from', 'date_to', 'date_ranges',
            ]);

            if (!$hasTarget && !$this->boolean('confirm')) {
                $validator->errors()->add(
                    'criteria',
                    'You must specify at least one deletion selector (lead_ids, ids, id_ranges, emails, email_domain, email_pattern, tag, tags, channel, status, date_from/date_to, date_ranges) or confirm: true.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'lead_ids.array'      => 'The lead_ids must be an array of IDs or range strings.',
            'ids.array'           => 'The ids must be an array of IDs or range strings.',
            'id_ranges.array'     => 'The id_ranges must be an array of range strings (e.g. ["101-199"]).',
            'emails.array'        => 'The emails must be an array of email addresses or domain patterns.',
            'email_domains.array' => 'The email_domains must be an array of domain strings.',
            'tags.array'          => 'The tags must be an array of tag names or slugs.',
            'date_ranges.array'   => 'The date_ranges must be an array of date range objects or strings.',
        ];
    }
}
