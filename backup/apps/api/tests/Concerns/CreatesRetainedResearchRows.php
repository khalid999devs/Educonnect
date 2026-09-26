<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Row builders for `research_topics` and `research_topic_sources`.
 *
 * The Research *feature* was removed in the information-architecture overhaul
 * (its model, factory, policy, actions, controllers and routes are all gone),
 * but the two tables were deliberately retained: dropping them is a
 * destructive migration, and `reading_status` is genuine reading-progress
 * signal that the Progress overview still reads.
 *
 * Retained tables still need their CHECK constraints, foreign keys and
 * cascade behaviour asserted, so these helpers insert rows at the query-builder
 * level. There is intentionally no Eloquent model to go through.
 */
trait CreatesRetainedResearchRows
{
    /** Insert one research topic and return its primary key. */
    protected function insertResearchTopic(int $userId, string $title = 'Retained topic'): int
    {
        $now = now();

        return (int) DB::table('research_topics')->insertGetId([
            'public_id' => Str::lower((string) Str::ulid()),
            'user_id' => $userId,
            'title' => $title,
            'description' => null,
            'keywords' => json_encode(['transformers', 'attention'], JSON_THROW_ON_ERROR),
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Attach a knowledge item to a research topic's reading list. */
    protected function insertResearchTopicSource(
        int $userId,
        int $topicId,
        int $knowledgeItemId,
        string $readingStatus = 'to_read',
    ): void {
        $now = now();

        DB::table('research_topic_sources')->insert([
            'user_id' => $userId,
            'research_topic_id' => $topicId,
            'knowledge_item_id' => $knowledgeItemId,
            'reading_status' => $readingStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
