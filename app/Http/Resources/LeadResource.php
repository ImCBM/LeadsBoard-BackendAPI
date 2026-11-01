<?php

namespace App\Http\Resources;

use App\Models\Lead;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms Lead model responses with Field-Level Authorization.
 *
 * Automatically masks restricted fields (e.g. contact_number) based
 * on the caller's active tier entitlements retrieved from tier_field_restrictions.
 */
class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Lead $lead */
        $lead = $this->resource;

        $tier = $this->resolveCallerTier($request);
        $restrictedFields = $this->getRestrictedFields($tier);

        return [
            'id' => $lead->id,
            'company_id' => $lead->company_id,
            'full_name' => $lead->full_name,
            'job_title' => $lead->job_title,
            'title_tier' => $lead->title_tier,
            'corporate_email' => $lead->corporate_email,
            'contact_number' => in_array('contact_number', $restrictedFields) ? null : $lead->contact_number,
            'email_status' => $lead->email_status,
            'executive_linkedin_url' => $lead->executive_linkedin_url,
            'ingestion_channel' => $lead->ingestion_channel,
            'status' => $lead->status,
            'notes' => $lead->notes,
            'company_name' => $lead->company_name,
            'clean_root_domain' => $lead->clean_root_domain,
            'website_status' => $lead->website_status,
            'company_linkedin_page' => $lead->company_linkedin_page,
            'industry_classification' => $lead->industry_classification,
            'employee_headcount' => $lead->employee_headcount,
            'hq_location' => $lead->hq_location,
            'country' => $lead->country,
            'tag_names' => $lead->tag_names,
            'tags' => $lead->tags,
            'company' => $lead->company,
            'created_at' => $lead->created_at,
            'updated_at' => $lead->updated_at,
        ];
    }

    /**
     * Get additional metadata for single resource responses.
     */
    public function with(Request $request): array
    {
        $tier = $this->resolveCallerTier($request);
        $restricted = $this->getRestrictedFields($tier);

        if (!empty($restricted)) {
            return [
                'meta' => [
                    'tier_restricted_fields' => $restricted,
                ],
            ];
        }

        return [];
    }

    /**
     * Create a resource collection with tier restriction metadata included.
     */
    public static function collectionWithRestrictions(mixed $resource, Request $request): AnonymousResourceCollection
    {
        $collection = static::collection($resource);

        $self = new static(null);
        $tier = $self->resolveCallerTier($request);
        $restricted = $self->getRestrictedFields($tier);

        if (!empty($restricted)) {
            $collection->additional([
                'meta' => [
                    'tier_restricted_fields' => $restricted,
                ],
            ]);
        }

        return $collection;
    }

    /**
     * Resolve caller's active Tier model from request.
     */
    public function resolveCallerTier(Request $request): ?Tier
    {
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey && $apiKey->tier instanceof Tier) {
            return $apiKey->tier;
        }

        $user = $request->user();
        if ($user) {
            return $user->getTier();
        }

        return null;
    }

    /**
     * Get list of restricted field names for the 'leads' resource under this tier.
     *
     * @return array<string>
     */
    public function getRestrictedFields(?Tier $tier): array
    {
        if (!$tier) {
            return [];
        }

        return $tier->fieldRestrictions()
            ->where('resource', 'leads')
            ->pluck('field_name')
            ->all();
    }
}
