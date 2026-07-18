<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the curated launch communities. These are real, platform-owned spaces —
 * not fabricated social proof. Communities start with no members and no posts;
 * the honest empty-community state is the truthful launch boundary. Community
 * curation moves to the admin console in a later phase.
 */
final class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $communities = [
            [
                'slug' => 'academic-writing',
                'name' => 'Academic Writing',
                'summary' => 'Structure essays, cite sources, and get feedback on drafts.',
                'topic' => 'Writing',
            ],
            [
                'slug' => 'study-skills',
                'name' => 'Study Skills',
                'summary' => 'Share revision techniques, focus routines, and exam preparation.',
                'topic' => 'Productivity',
            ],
            [
                'slug' => 'research-methods',
                'name' => 'Research Methods',
                'summary' => 'Discuss literature reviews, methodology, and managing sources.',
                'topic' => 'Research',
            ],
            [
                'slug' => 'ai-tools-for-learning',
                'name' => 'AI Tools for Learning',
                'summary' => 'Use AI responsibly for study, with academic integrity in mind.',
                'topic' => 'AI',
            ],
        ];

        foreach ($communities as $community) {
            if (DB::table('communities')->where('slug', $community['slug'])->exists()) {
                continue;
            }

            DB::table('communities')->insert([
                'slug' => $community['slug'],
                'name' => $community['name'],
                'summary' => $community['summary'],
                'description' => null,
                'topic' => $community['topic'],
                'visibility' => 'published',
                'is_seeded' => true,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
