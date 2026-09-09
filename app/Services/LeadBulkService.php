<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadBulkService
{
    /**
     * Bulk delete leads matching specified criteria.
     *
     * @param array $criteria
     * @return array{success: bool, deleted_count: int, deleted_ids: array, criteria: array}
     */
    public function bulkDelete(array $criteria): array
    {
        $query = Lead::query();

        // 1. Target by explicit IDs
        $ids = $criteria['lead_ids'] ?? ($criteria['ids'] ?? null);
        if (!empty($ids)) {
            $query->whereIn('id', (array) $ids);
        }

        // 2. Target by Emails
        if (!empty($criteria['emails'])) {
            $emails = array_map(fn($e) => Str::lower(trim($e)), (array) $criteria['emails']);
            $query->whereIn('corporate_email', $emails);
        }

        // 3. Target by Tag(s)
        $tag = $criteria['tag'] ?? null;
        $tags = $criteria['tags'] ?? null;
        if (!empty($tag)) {
            $query->byTag($tag);
        } elseif (!empty($tags)) {
            $query->byTags((array) $tags);
        }

        // 4. Target by Channel
        $channel = $criteria['channel'] ?? ($criteria['ingestion_channel'] ?? null);
        if (!empty($channel)) {
            $query->byChannel($channel);
        }

        // 5. Target by Status
        if (!empty($criteria['status'])) {
            $query->byStatus($criteria['status']);
        }

        // 6. Target by Date Range
        if (!empty($criteria['date_from']) || !empty($criteria['date_to'])) {
            $query->dateRange($criteria['date_from'] ?? null, $criteria['date_to'] ?? null);
        }

        // Collect matching IDs first
        $deletedIds = $query->pluck('id')->toArray();

        if (empty($deletedIds)) {
            return [
                'success'       => true,
                'deleted_count' => 0,
                'deleted_ids'   => [],
                'criteria'      => $criteria,
            ];
        }

        // Delete atomically in chunked transaction
        DB::transaction(function () use ($deletedIds) {
            foreach (array_chunk($deletedIds, 500) as $chunk) {
                // Delete pivot records first if needed, though foreign keys have cascadeOnDelete
                DB::table('lead_tag')->whereIn('lead_id', $chunk)->delete();
                Lead::whereIn('id', $chunk)->delete();
            }
        });

        return [
            'success'       => true,
            'deleted_count' => count($deletedIds),
            'deleted_ids'   => $deletedIds,
            'criteria'      => $criteria,
        ];
    }

    /**
     * Bulk apply tag operations across target leads.
     *
     * @param array $payload
     * @return array{success: bool, updated_count: int, lead_ids: array, operations: array}
     */
    public function bulkTag(array $payload): array
    {
        $query = Lead::query();

        // 1. Resolve targets
        $ids = $payload['lead_ids'] ?? ($payload['ids'] ?? null);
        if (!empty($ids)) {
            $query->whereIn('id', (array) $ids);
        } elseif (!empty($payload['emails'])) {
            $emails = array_map(fn($e) => Str::lower(trim($e)), (array) $payload['emails']);
            $query->whereIn('corporate_email', $emails);
        } elseif (!empty($payload['filter_tag'])) {
            $query->byTag($payload['filter_tag']);
        } elseif (!empty($payload['channel']) || !empty($payload['ingestion_channel'])) {
            $query->byChannel($payload['channel'] ?? $payload['ingestion_channel']);
        }

        $leads = $query->get();
        $targetIds = $leads->pluck('id')->toArray();

        if (empty($targetIds)) {
            return [
                'success'       => true,
                'updated_count' => 0,
                'lead_ids'      => [],
                'operations'    => [],
            ];
        }

        $addTags    = $payload['add_tags'] ?? ($payload['tags'] ?? null);
        $removeTags = $payload['remove_tags'] ?? null;
        $syncTags   = $payload['sync_tags'] ?? null;

        DB::transaction(function () use ($leads, $addTags, $removeTags, $syncTags) {
            foreach ($leads as $lead) {
                if ($syncTags !== null) {
                    $lead->syncTags($syncTags);
                } else {
                    if (!empty($addTags)) {
                        $lead->attachTags($addTags);
                    }
                    if (!empty($removeTags)) {
                        $lead->detachTags($removeTags);
                    }
                }
            }
        });

        return [
            'success'       => true,
            'updated_count' => count($targetIds),
            'lead_ids'      => $targetIds,
            'operations'    => [
                'added'   => $addTags,
                'removed' => $removeTags,
                'synced'  => $syncTags,
            ],
        ];
    }
}
