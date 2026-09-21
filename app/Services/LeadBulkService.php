<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        $query = $this->buildTargetQuery($criteria);

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
        $query = $this->buildTargetQuery($payload);

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

    /**
     * Build the query for target leads using compound (AND) filtering across selectors.
     */
    public function buildTargetQuery(array $criteria): Builder
    {
        $query = Lead::query();

        // 1. Target by explicit IDs & ID ranges
        $this->applyIdCriteria($query, $criteria);

        // 2. Target by Emails, Email Domains & Wildcard Patterns
        $this->applyEmailCriteria($query, $criteria);

        // 3. Target by Tag(s)
        $tag = $criteria['tag'] ?? ($criteria['filter_tag'] ?? null);
        $tags = $criteria['tags'] ?? null;
        if (!empty($tag)) {
            $query->byTag($tag);
        } elseif (!empty($tags) && is_array($tags)) {
            $query->byTags($tags);
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

        // 6. Target by Date Range & Multi-Date Ranges
        $this->applyDateCriteria($query, $criteria);

        return $query;
    }

    /**
     * Apply ID filters: exact IDs, id_ranges (e.g. "101-199"), and id_from/id_to.
     */
    protected function applyIdCriteria(Builder $query, array $criteria): void
    {
        $rawIds = array_merge(
            (array) ($criteria['lead_ids'] ?? []),
            (array) ($criteria['ids'] ?? []),
            (array) ($criteria['id_ranges'] ?? [])
        );

        $singleIds = [];
        $ranges = [];

        foreach ($rawIds as $item) {
            if (is_numeric($item)) {
                $singleIds[] = (int) $item;
            } elseif (is_string($item)) {
                // Match pattern "101-199", "101..199", "101:199"
                if (preg_match('/^(\d+)\s*(?:-|\.\.|:)\s*(\d+)$/', trim($item), $matches)) {
                    $ranges[] = [(int) $matches[1], (int) $matches[2]];
                }
            }
        }

        if (!empty($criteria['id_from']) || !empty($criteria['id_to'])) {
            $from = (int) ($criteria['id_from'] ?? 1);
            $to = !empty($criteria['id_to']) ? (int) $criteria['id_to'] : null;
            if ($to !== null) {
                $ranges[] = [$from, $to];
            } else {
                $ranges[] = [$from, PHP_INT_MAX];
            }
        }

        if (empty($singleIds) && empty($ranges)) {
            return;
        }

        $query->where(function ($q) use ($singleIds, $ranges) {
            $first = true;
            if (!empty($singleIds)) {
                $q->whereIn('id', array_values(array_unique($singleIds)));
                $first = false;
            }
            foreach ($ranges as [$from, $to]) {
                $min = min($from, $to);
                $max = max($from, $to);
                if ($first) {
                    $q->whereBetween('id', [$min, $max]);
                    $first = false;
                } else {
                    $q->orWhereBetween('id', [$min, $max]);
                }
            }
        });
    }

    /**
     * Apply Email filters: exact emails, email domains (@domain.com), and SQL LIKE patterns.
     */
    protected function applyEmailCriteria(Builder $query, array $criteria): void
    {
        $exactEmails = [];
        $domainPatterns = [];
        $likePatterns = [];

        // Collect from email_domain / email_domains
        if (!empty($criteria['email_domain'])) {
            $domainPatterns[] = ltrim(trim($criteria['email_domain']), '@');
        }
        if (!empty($criteria['email_domains']) && is_array($criteria['email_domains'])) {
            foreach ($criteria['email_domains'] as $d) {
                $domainPatterns[] = ltrim(trim($d), '@');
            }
        }

        // Collect from email_pattern / email_patterns
        if (!empty($criteria['email_pattern'])) {
            $likePatterns[] = trim($criteria['email_pattern']);
        }
        if (!empty($criteria['email_patterns']) && is_array($criteria['email_patterns'])) {
            foreach ($criteria['email_patterns'] as $p) {
                $likePatterns[] = trim($p);
            }
        }

        // Collect from emails array
        if (!empty($criteria['emails']) && is_array($criteria['emails'])) {
            foreach ($criteria['emails'] as $item) {
                $trimmed = Str::lower(trim((string) $item));
                if (str_starts_with($trimmed, '@')) {
                    // e.g. "@demodomain.com"
                    $domainPatterns[] = ltrim($trimmed, '@');
                } elseif (str_contains($trimmed, '%') || str_contains($trimmed, '*')) {
                    // e.g. "%@demodomain%" or "*@demodomain.com"
                    $likePatterns[] = str_replace('*', '%', $trimmed);
                } else {
                    $exactEmails[] = $trimmed;
                }
            }
        }

        $domainPatterns = array_values(array_unique(array_filter($domainPatterns)));
        $likePatterns = array_values(array_unique(array_filter($likePatterns)));
        $exactEmails = array_values(array_unique(array_filter($exactEmails)));

        if (empty($exactEmails) && empty($domainPatterns) && empty($likePatterns)) {
            return;
        }

        $query->where(function ($q) use ($exactEmails, $domainPatterns, $likePatterns) {
            $first = true;
            if (!empty($exactEmails)) {
                $q->whereIn('corporate_email', $exactEmails);
                $first = false;
            }

            foreach ($domainPatterns as $domain) {
                // '%@' . $domain matches any email address ending with @domain (protects other domains)
                $pattern = '%@' . $domain;
                if ($first) {
                    $q->where('corporate_email', 'LIKE', $pattern);
                    $first = false;
                } else {
                    $q->orWhere('corporate_email', 'LIKE', $pattern);
                }
            }

            foreach ($likePatterns as $pattern) {
                if ($first) {
                    $q->where('corporate_email', 'LIKE', $pattern);
                    $first = false;
                } else {
                    $q->orWhere('corporate_email', 'LIKE', $pattern);
                }
            }
        });
    }

    /**
     * Apply Date Range filters: single date_from/date_to and multiple date_ranges.
     */
    protected function applyDateCriteria(Builder $query, array $criteria): void
    {
        $ranges = [];

        if (!empty($criteria['date_from']) || !empty($criteria['date_to'])) {
            $ranges[] = [
                'from' => $criteria['date_from'] ?? null,
                'to'   => $criteria['date_to'] ?? null,
            ];
        }

        if (!empty($criteria['date_ranges']) && is_array($criteria['date_ranges'])) {
            foreach ($criteria['date_ranges'] as $r) {
                if (is_array($r)) {
                    $ranges[] = [
                        'from' => $r['from'] ?? ($r['date_from'] ?? null),
                        'to'   => $r['to'] ?? ($r['date_to'] ?? null),
                    ];
                } elseif (is_string($r)) {
                    // e.g. "2026-01-01..2026-01-31" or "2026-01-01:2026-01-31"
                    if (preg_match('/^([\d-]+)\s*(?:\.\.|:)\s*([\d-]+)$/', trim($r), $m)) {
                        $ranges[] = ['from' => $m[1], 'to' => $m[2]];
                    }
                }
            }
        }

        if (empty($ranges)) {
            return;
        }

        $query->where(function ($q) use ($ranges) {
            $first = true;
            foreach ($ranges as $range) {
                $from = $range['from'] ?? null;
                $to = $range['to'] ?? null;

                $applyRange = function ($subQuery) use ($from, $to) {
                    if ($from) {
                        $subQuery->where('created_at', '>=', Carbon::parse($from)->startOfDay());
                    }
                    if ($to) {
                        $subQuery->where('created_at', '<=', Carbon::parse($to)->endOfDay());
                    }
                };

                if ($first) {
                    $q->where($applyRange);
                    $first = false;
                } else {
                    $q->orWhere($applyRange);
                }
            }
        });
    }
}
