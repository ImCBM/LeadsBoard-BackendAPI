<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            // System / Developer tags
            [
                'name'        => 'Test',
                'slug'        => 'test',
                'type'        => Tag::TYPE_SYSTEM,
                'color'       => '#ef4444',
                'description' => 'System tag for automated test leads and developer benchmarks.',
            ],
            [
                'name'        => 'Demo',
                'slug'        => 'demo',
                'type'        => Tag::TYPE_SYSTEM,
                'color'       => '#f59e0b',
                'description' => 'Demonstration and sample staging leads.',
            ],
            [
                'name'        => 'Sample',
                'slug'        => 'sample',
                'type'        => Tag::TYPE_SYSTEM,
                'color'       => '#64748b',
                'description' => 'Initial sample data imported during onboarding.',
            ],
            // Public tags
            [
                'name'        => 'VIP',
                'slug'        => 'vip',
                'type'        => Tag::TYPE_PUBLIC,
                'color'       => '#10b981',
                'description' => 'High-value executive prospect.',
            ],
            [
                'name'        => 'High Priority',
                'slug'        => 'high-priority',
                'type'        => Tag::TYPE_PUBLIC,
                'color'       => '#8b5cf6',
                'description' => 'Urgent sales follow-up requested.',
            ],
            [
                'name'        => 'Q3 Campaign',
                'slug'        => 'q3-campaign',
                'type'        => Tag::TYPE_PUBLIC,
                'color'       => '#06b6d4',
                'description' => 'Outreach batch for Q3 pipeline generation.',
            ],
        ];

        foreach ($tags as $tagData) {
            Tag::updateOrCreate(['slug' => $tagData['slug']], $tagData);
        }
    }
}
