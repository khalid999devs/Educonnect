<?php

declare(strict_types=1);

namespace Tests\Feature\Study;

use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Study\Concerns\InteractsWithStudy;
use Tests\TestCase;

final class StudyListTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithStudy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        Queue::fake();
    }

    public function test_artifacts_paginate_newest_first_with_a_working_cursor(): void
    {
        $owner = User::factory()->create();
        $items = [];

        foreach (range(0, 4) as $offset) {
            $item = $this->studyableItem($owner);
            $items[] = $item;
            StudyArtifact::factory()
                ->forItem($item)
                ->ready()
                ->create(['created_at' => now()->subMinutes(10 - $offset), 'updated_at' => now()->subMinutes(10 - $offset)]);
        }

        $this->actingAs($owner, 'web');

        $first = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts?per_page=2');
        $first->assertOk()->assertJsonCount(2, 'data');
        self::assertSame(5, $first->json('meta.summary.total'));
        self::assertSame(5, $first->json('meta.summary.ready'));

        $cursor = $first->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);

        $second = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?per_page=2&cursor='.urlencode($cursor));
        $second->assertOk()->assertJsonCount(2, 'data');

        $firstIds = array_column((array) $first->json('data'), 'id');
        $secondIds = array_column((array) $second->json('data'), 'id');
        self::assertSame([], array_intersect($firstIds, $secondIds));
        self::assertCount(5, $items);
    }

    public function test_the_oldest_first_sort_reverses_the_page(): void
    {
        $owner = User::factory()->create();
        $oldest = StudyArtifact::factory()
            ->forItem($this->studyableItem($owner))
            ->ready()
            ->create(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $newest = StudyArtifact::factory()
            ->forItem($this->studyableItem($owner))
            ->ready()
            ->create(['created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($owner, 'web');

        $descending = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts?sort=-created_at');
        $ascending = $this->withHeaders($this->headers())->getJson('/api/v1/study/artifacts?sort=created_at');

        $descending->assertOk()->assertJsonPath('data.0.id', (string) $newest->public_id);
        $ascending->assertOk()->assertJsonPath('data.0.id', (string) $oldest->public_id);
    }

    public function test_kind_status_and_item_filters_narrow_the_list(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        StudyArtifact::factory()->forItem($item)->kind(StudyArtifactKind::Summary)->ready()->create();
        StudyArtifact::factory()->forItem($item)->kind(StudyArtifactKind::ExamQuestions)->failed()->create();

        $otherItem = $this->studyableItem($owner);
        StudyArtifact::factory()->forItem($otherItem)->kind(StudyArtifactKind::QuickLearn)->queued()->create();

        $this->actingAs($owner, 'web');

        $byKind = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?kind=exam_questions');
        $byStatus = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?status=ready');
        $byItem = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?item_id='.$item->public_id);

        $byKind->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'exam_questions');
        $byStatus->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'ready');
        $byItem->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_failed_artifact_never_lists_a_payload(): void
    {
        $owner = User::factory()->create();
        StudyArtifact::factory()
            ->forItem($this->studyableItem($owner))
            ->failed('The AI provider was unavailable.')
            ->create();

        $this->actingAs($owner, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?status=failed')
            ->assertOk()
            ->assertJsonPath('data.0.payload', null)
            ->assertJsonPath('data.0.failure_reason', 'The AI provider was unavailable.');
    }

    public function test_tampered_cursors_and_unknown_filters_are_rejected(): void
    {
        $owner = User::factory()->create();
        StudyArtifact::factory()->forItem($this->studyableItem($owner))->ready()->create();
        $this->actingAs($owner, 'web');

        $probes = [
            '/api/v1/study/artifacts?cursor=not-a-cursor',
            '/api/v1/study/artifacts?cursor='.urlencode(base64_encode('{"id":1}')),
            '/api/v1/study/artifacts?sort=payload',
            '/api/v1/study/artifacts?kind=flashcards',
            '/api/v1/study/artifacts?status=pending',
            '/api/v1/study/artifacts?per_page=500',
            '/api/v1/study/artifacts?owner=1',
        ];

        foreach ($probes as $probe) {
            $response = $this->withHeaders($this->headers())->getJson($probe);
            $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
        }
    }

    public function test_a_cursor_minted_for_one_sort_is_refused_on_another(): void
    {
        $owner = User::factory()->create();

        foreach (range(0, 2) as $offset) {
            StudyArtifact::factory()
                ->forItem($this->studyableItem($owner))
                ->ready()
                ->create(['created_at' => now()->subMinutes($offset), 'updated_at' => now()->subMinutes($offset)]);
        }

        $this->actingAs($owner, 'web');

        $cursor = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?per_page=1&sort=-created_at')
            ->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/study/artifacts?per_page=1&sort=-updated_at&cursor='.urlencode($cursor));

        $this->assertApiError($response, 422, ApiErrorCode::ValidationFailed);
    }
}
