<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Tag;
use Illuminate\Console\Command;

class RetroactivelyTagLeadsCommand extends Command
{
    protected $signature = 'leads:tag
        {--tag=test          : Tag name to apply}
        {--type=             : Tag type (public or system)}
        {--channel=          : Filter leads by ingestion_channel (e.g. n8n, api, manual)}
        {--company-pattern=  : Filter leads by company name pattern (e.g. %Test%, [TEST]%)}
        {--email-pattern=    : Filter leads by email pattern (e.g. %@test.com)}
        {--ids=              : Comma-separated lead IDs}
        {--all               : Tag all leads in the database}
        {--dry-run           : Show matching leads without applying tags}';

    protected $description = 'Retroactively attach a tag to existing leads by channel, company pattern, or IDs';

    public function handle(): int
    {
        $tagName   = $this->option('tag') ?: 'test';
        $tagType   = $this->option('type') ?: (in_array(strtolower($tagName), ['test', 'demo', 'sample']) ? Tag::TYPE_SYSTEM : Tag::TYPE_PUBLIC);
        $channel   = $this->option('channel');
        $companyPat = $this->option('company-pattern');
        $emailPat  = $this->option('email-pattern');
        $idsOption = $this->option('ids');
        $isAll     = $this->option('all');
        $isDryRun  = $this->option('dry-run');

        if (!$channel && !$companyPat && !$emailPat && !$idsOption && !$isAll) {
            $this->error('Please specify at least one selector: --channel=n8n, --company-pattern=%Test%, --email-pattern=%@test.com, --ids=1,2,3, or --all');
            return self::FAILURE;
        }

        $query = Lead::query();

        if ($idsOption) {
            $ids = array_filter(array_map('intval', explode(',', $idsOption)));
            $query->whereIn('id', $ids);
        }

        if ($channel) {
            $query->byChannel($channel);
        }

        if ($companyPat) {
            $query->whereHas('company', fn($q) => $q->where('name', 'LIKE', $companyPat));
        }

        if ($emailPat) {
            $query->where('corporate_email', 'LIKE', $emailPat);
        }

        $leads = $query->get();

        $this->info("Found {$leads->count()} matching leads.");

        if ($leads->isEmpty()) {
            $this->warn('No leads matched the given criteria.');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn("🔍 DRY RUN — The following lead IDs would be tagged with '{$tagName}' ({$tagType}):");
            $this->line(implode(', ', $leads->pluck('id')->toArray()));
            return self::SUCCESS;
        }

        $tag = Tag::findOrCreateByName($tagName, $tagType);

        $bar = $this->output->createProgressBar($leads->count());
        $bar->start();

        foreach ($leads as $lead) {
            $lead->attachTags([$tag]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Successfully tagged {$leads->count()} leads with '{$tag->name}' (ID: {$tag->id}).");

        return self::SUCCESS;
    }
}
