<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkTagLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'channel'           => 'sometimes|string|max:50',
            'ingestion_channel' => 'sometimes|string|max:50',
            'status'            => 'sometimes|string|in:new,reviewed,qualified,rejected',
            'filter_tag'        => 'sometimes|string|max:100',
            'date_from'         => 'sometimes|date',
            'date_to'           => 'sometimes|date',
            'date_ranges'       => 'sometimes|array|min:1|max:50',
            
            // Tag actions
            'add_tags'          => 'sometimes',
            'tags'              => 'sometimes',
            'remove_tags'       => 'sometimes',
            'sync_tags'         => 'sometimes',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasTarget = $this->hasAny([
                'lead_ids', 'ids', 'id_ranges', 'id_from', 'id_to',
                'emails', 'email_domain', 'email_domains', 'email_pattern', 'email_patterns',
                'channel', 'ingestion_channel', 'status', 'filter_tag',
                'date_from', 'date_to', 'date_ranges',
            ]);
            if (!$hasTarget) {
                $validator->errors()->add(
                    'targets',
                    'You must specify target leads via lead_ids, ids, id_ranges, emails, email_domain, channel, status, date_from/date_to, or filter_tag.'
                );
            }

            $hasAction = $this->hasAny(['add_tags', 'tags', 'remove_tags', 'sync_tags']);
            if (!$hasAction) {
                $validator->errors()->add(
                    'actions',
                    'You must provide at least one tag operation: add_tags, tags, remove_tags, or sync_tags.'
                );
            }
        });
    }
}
