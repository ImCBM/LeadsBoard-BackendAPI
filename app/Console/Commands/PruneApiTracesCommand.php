<?php

namespace App\Console\Commands;

use App\Models\ApiTrace;
use Illuminate\Console\Command;

/**
 * Prune old API trace records based on the configured retention period.
 *
 * This command should be scheduled to run daily to keep the
 * api_traces table from growing unbounded.
 */
class PruneApiTracesCommand extends Command
{
    protected $signature = 'api:prune-traces {--days= : Override the default retention days}';

    protected $description = 'Delete API trace records older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('tiers.traces.retention_days', 30));

        $this->info("Pruning API traces older than {$days} days...");

        $deleted = ApiTrace::prune($days);

        $this->info("Deleted {$deleted} trace records.");

        return self::SUCCESS;
    }
}
