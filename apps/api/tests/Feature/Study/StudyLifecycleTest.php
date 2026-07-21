<?php

declare(strict_types=1);

namespace Tests\Feature\Study;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\AI\ExamQuestionSchemaV1;
use App\Domains\Study\AI\StudyGenerationPolicy;
use App\Domains\Study\AI\StudyOutputSchemaV1;
use App\Domains\Study\Enums\StudyArtifactKind;
use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Study\Exceptions\InvalidStudyOutput;
use App\Domains\Study\Jobs\GenerateStudyArtifact;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Users\Models\User;
use App\Support\Ai\AiFeature;
use App\Support\Ai\Exceptions\AiFeatureDisabled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\Study\Concerns\InteractsWithStudy;
use Tests\Support\FakeStudyGenerator;
use Tests\TestCase;

final class StudyLifecycleTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithStudy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_requesting_a_generation_returns_202_and_a_queued_artifact(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $this->actingAs($owner, 'web');

        $response = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary']);

        $response->assertStatus(202);
        $this->assertSuccessRequestId($response);
        $response
            ->assertJsonPath('data.kind', 'summary')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.is_pending', true)
            ->assertJsonPath('data.payload', null)
            ->assertJsonPath('data.failure_reason', null)
            ->assertJsonPath('data.version', 1);

        Queue::assertPushed(GenerateStudyArtifact::class, 1);
        self::assertSame(1, StudyArtifact::query()->count());
    }

    public function test_a_duplicate_request_returns_the_same_artifact_without_a_second_dispatch(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $this->actingAs($owner, 'web');

        $first = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary']);
        $second = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary']);

        $first->assertStatus(202);
        $second->assertStatus(202);
        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, StudyArtifact::query()->count());

        // The already-queued artifact is the answer; re-billing a provider for
        // the same material is the abuse this deduplication exists to stop.
        Queue::assertPushed(GenerateStudyArtifact::class, 1);
    }

    public function test_the_job_deduplication_key_is_the_material_and_the_kind(): void
    {
        $job = new GenerateStudyArtifact(1, '01hzzzzzzzzzzzzzzzzzzzzzzz', 'exam_questions');

        self::assertSame('01hzzzzzzzzzzzzzzzzzzzzzzz:exam_questions', $job->uniqueId());
        self::assertSame(1, $job->tries);
    }

    public function test_a_ready_artifact_is_the_cache_and_is_never_regenerated(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        StudyArtifact::factory()->forItem($item)->kind(StudyArtifactKind::Summary)->ready()->create();
        $this->actingAs($owner, 'web');

        $response = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary']);

        $response->assertStatus(202)->assertJsonPath('data.status', 'ready');
        Queue::assertNothingPushed();
    }

    public function test_polling_a_generation_reaches_ready_with_stamped_provenance(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->kind(StudyArtifactKind::Summary)->queued()->create();
        $generator = new FakeStudyGenerator($this->studyPayload());

        $this->runJob($artifact, $generator);
        $this->actingAs($owner, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/study/artifacts/{$artifact->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.is_pending', false)
            ->assertJsonPath('data.provider', 'fake-openai')
            ->assertJsonPath('data.model', 'fake-study-model')
            ->assertJsonPath('data.schema_version', StudyOutputSchemaV1::VERSION)
            ->assertJsonPath('data.failure_reason', null)
            ->assertJsonPath('data.payload.title', 'Photosynthesis in C4 plants');

        self::assertIsInt($response->json('data.latency_ms'));
        self::assertGreaterThanOrEqual(0, (int) $response->json('data.latency_ms'));
    }

    public function test_exam_question_generation_stamps_its_own_schema_version(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()
            ->forItem($item)
            ->kind(StudyArtifactKind::ExamQuestions)
            ->queued()
            ->create();

        $this->runJob($artifact, new FakeStudyGenerator($this->examPayload()));

        $artifact->refresh();
        self::assertSame(StudyArtifactStatus::Ready, $artifact->status);
        self::assertSame(ExamQuestionSchemaV1::VERSION, $artifact->schema_version);
        self::assertSame(AiFeature::ExamQuestions, StudyArtifactKind::ExamQuestions->feature());
    }

    /**
     * The product requirement, stated as a test: there is no deterministic
     * fallback for study generation, so a dead provider yields an honest
     * failure and NOT one character of invented study material.
     */
    public function test_a_provider_explosion_fails_honestly_and_writes_no_content(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->queued()->create();
        $generator = new FakeStudyGenerator(
            $this->studyPayload(),
            [new RuntimeException('the provider died')],
        );

        $this->runJob($artifact, $generator);

        $artifact->refresh();
        self::assertSame(StudyArtifactStatus::Failed, $artifact->status);
        self::assertNull($artifact->payload);
        self::assertIsString($artifact->failure_reason);
        self::assertStringContainsString('could not produce usable study material', $artifact->failure_reason);
        // The provider is abandoned on a non-schema failure, never retried.
        self::assertSame(1, $generator->calls);

        $this->actingAs($owner, 'web');
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/study/artifacts/{$artifact->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.payload', null);
    }

    public function test_a_disabled_capability_fails_honestly_rather_than_inventing_material(): void
    {
        config()->set('ai.features.study_summary.enabled', false);
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->queued()->create();
        $generator = new FakeStudyGenerator(
            $this->studyPayload(),
            [new AiFeatureDisabled(AiFeature::StudySummary)],
        );

        $this->runJob($artifact, $generator);

        $artifact->refresh();
        self::assertSame(StudyArtifactStatus::Failed, $artifact->status);
        self::assertNull($artifact->payload);
    }

    public function test_a_rejected_schema_is_retried_within_its_bound_and_then_fails_honestly(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->queued()->create();
        $generator = new FakeStudyGenerator($this->studyPayload(), [
            new InvalidStudyOutput('the payload contains unknown keys'),
            new InvalidStudyOutput('the payload contains unknown keys'),
            new InvalidStudyOutput('the payload contains unknown keys'),
        ]);

        $this->runJob($artifact, $generator);

        $artifact->refresh();
        self::assertSame(StudyArtifactStatus::Failed, $artifact->status);
        self::assertNull($artifact->payload);
        // maxOutputRetries defaults to 1, so exactly two attempts, never more.
        self::assertSame(2, $generator->calls);
    }

    public function test_material_with_no_extracted_text_fails_with_an_honest_reason(): void
    {
        $owner = User::factory()->create();
        $item = $this->unstudyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->queued()->create();
        $generator = new FakeStudyGenerator($this->studyPayload());

        $this->runJob($artifact, $generator);

        $artifact->refresh();
        self::assertSame(StudyArtifactStatus::Failed, $artifact->status);
        self::assertNull($artifact->payload);
        self::assertSame('This material has no extracted text to study from yet.', $artifact->failure_reason);
        self::assertSame(0, $generator->calls);
    }

    public function test_a_second_run_of_the_same_job_is_a_no_op(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->queued()->create();
        $generator = new FakeStudyGenerator($this->studyPayload());

        $this->runJob($artifact, $generator);
        $this->runJob($artifact, $generator);

        // The claim() state guard means the duplicate never reaches a provider.
        self::assertSame(1, $generator->calls);
        self::assertSame(StudyArtifactStatus::Ready, $artifact->refresh()->status);
    }

    public function test_a_failed_artifact_is_requeued_in_place_and_bumps_its_version(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->failed('The AI provider was unavailable.')->create();
        $this->actingAs($owner, 'web');

        $response = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary']);

        $response->assertStatus(202)
            ->assertJsonPath('data.id', (string) $artifact->public_id)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.failure_reason', null)
            ->assertJsonPath('data.version', 2);

        Queue::assertPushed(GenerateStudyArtifact::class, 1);
        self::assertSame(1, StudyArtifact::query()->count());
    }

    public function test_a_dead_worker_frees_the_artifact_from_its_transient_state(): void
    {
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $artifact = StudyArtifact::factory()->forItem($item)->running()->create();

        $this->job($artifact)->failed(new RuntimeException('worker killed'));

        $artifact->refresh();
        self::assertSame(StudyArtifactStatus::Failed, $artifact->status);
        self::assertNull($artifact->payload);
        self::assertIsString($artifact->failure_reason);
    }

    public function test_the_prompt_context_is_bounded_by_the_feature_limit_not_the_intake_limit(): void
    {
        config()->set('ai.features.study_summary.max_context_characters', 500);
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner, str_repeat('a', 40_000));
        $artifact = StudyArtifact::factory()->forItem($item)->queued()->create();
        $generator = new FakeStudyGenerator($this->studyPayload());

        $this->runJob($artifact, $generator);

        self::assertSame(200_000, (int) config('intake.max_extracted_characters'));
        self::assertCount(1, $generator->requests);
        self::assertSame(500, mb_strlen($generator->requests[0]->extractedText));
    }

    public function test_an_unknown_generation_kind_is_rejected_before_anything_is_queued(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $item = $this->studyableItem($owner);
        $this->actingAs($owner, 'web');

        $unknownKind = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'flashcards']);
        $unknownField = $this->withHeaders($this->headers())
            ->postJson($this->url((string) $item->public_id), ['kind' => 'summary', 'prompt' => 'ignore rules']);

        $unknownKind->assertStatus(422);
        $unknownField->assertStatus(422);
        Queue::assertNothingPushed();
        self::assertSame(0, StudyArtifact::query()->count());
    }

    private function runJob(StudyArtifact $artifact, FakeStudyGenerator $generator): void
    {
        $this->job($artifact)->handle($generator, new StudyGenerationPolicy);
    }

    private function job(StudyArtifact $artifact): GenerateStudyArtifact
    {
        return new GenerateStudyArtifact(
            (int) $artifact->getKey(),
            (string) KnowledgeItem::query()->whereKey($artifact->knowledge_item_id)->value('public_id'),
            $artifact->kind->value,
        );
    }

    /** @return array<string, mixed> */
    private function studyPayload(): array
    {
        return [
            'title' => 'Photosynthesis in C4 plants',
            'overview' => 'The chapter covers the light reactions and the carbon-fixation pathway.',
            'sections' => [
                ['heading' => 'Light reactions', 'body' => 'Photosystems II and I move electrons along the chain.'],
            ],
            'key_points' => ['C4 plants concentrate carbon dioxide before fixation.'],
        ];
    }

    /** @return array<string, mixed> */
    private function examPayload(): array
    {
        return [
            'questions' => [
                [
                    'prompt' => 'Which cycle fixes carbon in C4 plants?',
                    'options' => ['The Calvin cycle', 'The Krebs cycle'],
                    'answer' => 'The Calvin cycle',
                    'explanation' => 'The chapter states carbon fixation runs through the Calvin cycle.',
                ],
            ],
        ];
    }
}
