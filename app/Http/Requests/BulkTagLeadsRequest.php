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
            'lead_ids.*'        => 'integer|min:1',
            'ids'               => 'sometimes|array|min:1|max:1000',
            'ids.*'             => 'integer|min:1',
            'emails'            => 'sometimes|array|min:1|max:1000',
            'emails.*'          => 'email|max:255',
            'channel'           => 'sometimes|string|max:50',
            'ingestion_channel' => 'sometimes|string|max:50',
            'filter_tag'        => 'sometimes|string|max:100',
            
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
            $hasTarget = $this->hasAny(['lead_ids', 'ids', 'emails', 'channel', 'ingestion_channel', 'filter_tag']);
            if (!$hasTarget) {
                $validator->errors()->add(
                    'targets',
                    'You must specify target leads via lead_ids, ids, emails, or filter_tag.'
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
