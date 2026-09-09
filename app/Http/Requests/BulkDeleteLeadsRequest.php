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
            'lead_ids.*'        => 'integer|min:1',
            'ids'               => 'sometimes|array|min:1|max:1000',
            'ids.*'             => 'integer|min:1',
            'emails'            => 'sometimes|array|min:1|max:1000',
            'emails.*'          => 'email|max:255',
            'tag'               => 'sometimes|string|max:100',
            'tags'              => 'sometimes|array|min:1|max:50',
            'tags.*'            => 'string|max:100',
            'channel'           => 'sometimes|string|max:50',
            'ingestion_channel' => 'sometimes|string|max:50',
            'status'            => 'sometimes|string|in:new,reviewed,qualified,rejected',
            'date_from'         => 'sometimes|date',
            'date_to'           => 'sometimes|date',
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
                'lead_ids', 'ids', 'emails', 'tag', 'tags',
                'channel', 'ingestion_channel', 'status'
            ]);

            if (!$hasTarget && !$this->boolean('confirm')) {
                $validator->errors()->add(
                    'criteria',
                    'You must specify at least one deletion selector (lead_ids, ids, emails, tag, tags, channel, status) or confirm: true.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'lead_ids.array' => 'The lead_ids must be an array of integers.',
            'ids.array'      => 'The ids must be an array of integers.',
            'emails.array'   => 'The emails must be an array of email addresses.',
            'tags.array'     => 'The tags must be an array of tag names or slugs.',
        ];
    }
}
