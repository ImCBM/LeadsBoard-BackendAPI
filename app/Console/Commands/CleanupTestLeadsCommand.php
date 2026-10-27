<?php

namespace App\Console\Commands;

use App\Services\LeadBulkService;
use Illuminate\Console\Command;

class CleanupTestLeadsCommand extends Command
{
    protected $signature = 'leads:cleanup-test
        {--tag=test          : Tag name to delete}
        {--channel=          : Ingestion channel to delete}
        {--company-pattern=  : Company name pattern to delete}
        {--force             : Force deletion without interactive confirmation}
        {--dry-run           : Show leads that would be deleted without deleting}';

    protected $description = 'Wipe test leads from the database by tag, channel, or company pattern';

    public function handle(LeadBulkService $bulkService): int
    {
        $tag        = $this->option('tag') ?: 'test';
        $channel    = $this->option('channel');
        $companyPat = $this->option('company-pattern');
        $isForce    = $this->option('force');
        $isDryRun   = $this->option('dry-run');

        $criteria = [];
        if ($tag) $criteria['tag'] = $tag;
        if ($channel) $criteria['channel'] = $channel;

        if ($isDryRun) {
            $this->info("🔍 DRY RUN — Checking leads matching criteria: " . json_encode($criteria));
        }

        // Preview count
        $query = \App\Models\Lead::query();
        if ($tag) $query->byTag($tag);
        if ($channel) $query->byChannel($channel);
        if ($companyPat) $query->whereHas('company', fn($q) => $q->where('name', 'LIKE', $companyPat));

        $count = $query->count();

        if ($count === 0) {
            $this->info("No test leads found matching the specified criteria.");
            return self::SUCCESS;
        }

        $this->warn("Found {$count} lead(s) matching criteria.");

        if ($isDryRun) {
            $ids = $query->pluck('id')->toArray();
            $this->line("Matching Lead IDs: " . implode(', ', $ids));
            return self::SUCCESS;
        }

        if (!$isForce && !$this->confirm("Are you sure you want to permanently delete {$count} lead(s)?")) {
            $this->info("Operation cancelled.");
            return self::SUCCESS;
        }

        $result = $bulkService->bulkDelete($criteria);

        $this->info("✅ Successfully deleted {$result['deleted_count']} test leads.");

        return self::SUCCESS;
    }
}
