<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;

final class IntakeLifecycleTest extends TestCase
{
    use InteractsWithIntake;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        $this->fakeDns();
    }

    public function test_link_submission_returns_observable_queued_status_and_dispatches_one_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/links', [
                'url' => 'https://university.example.edu/syllabus',
                'context' => 'Syllabus for my research methods course.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source.type', 'link')
            ->assertJsonPath('data.source.url', 'https://university.example.edu/syllabus')
            ->assertJsonPath('data.state', 'queued')
            ->assertJsonPath('data.attempts', 0)
            ->assertJsonPath('data.failure_code', null)
            ->assertJsonPath('data.events.0.event', 'created')
            ->assertJsonPath('data.events.1.event', 'queued');

        Queue::assertPushed(ProcessIntakeItem::class, 1);

        $itemId = $response->json('data.id');
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/intake/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.state', 'queued');
    }

    public function test_unsafe_links_are_rejected_before_any_record_exists(): void
    {
        Queue::fake();
        $this->fakeDns([
            'internal.example.edu' => ['10.0.0.8'],
            'metadata.example.edu' => ['169.254.169.254'],
        ]);
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        foreach ([
            'http://university.example.edu/plain-http',
            'https://internal.example.edu/private',
            'https://metadata.example.edu/latest',
            'https://93.184.216.34/ip-literal',
            'https://user:secret@university.example.edu/credentials',
            'https://university.example.edu:8443/odd-port',
        ] as $url) {
            $this->withHeaders($this->headers())
                ->postJson('/api/v1/intake/links', ['url' => $url])
                ->assertUnprocessable();
        }

        self::assertSame(0, IntakeItem::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_file_submission_requires_an_owned_ready_extractable_file_resource(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $readyText = Resource::factory()->file()->create(['user_id' => $user->getKey(), 'title' => 'Lecture notes']);
        StoredFile::factory()->forResource($readyText)->state(['declared_mime_type' => 'text/plain'])->ready()->create();

        $readyPdf = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        StoredFile::factory()->forResource($readyPdf)->state(['declared_mime_type' => 'application/pdf'])->ready()->create();

        $readyImage = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        StoredFile::factory()->forResource($readyImage)->state(['declared_mime_type' => 'image/png'])->ready()->create();

        $pendingFile = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        StoredFile::factory()->forResource($pendingFile)->create(['declared_mime_type' => 'text/plain']);

        $linkResource = Resource::factory()->link()->create(['user_id' => $user->getKey()]);
        $foreign = Resource::factory()->file()->create(['user_id' => $other->getKey()]);
        StoredFile::factory()->forResource($foreign)->state(['declared_mime_type' => 'text/plain'])->ready()->create();

        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $readyText->public_id])
            ->assertCreated()
            ->assertJsonPath('data.source.type', 'file')
            ->assertJsonPath('data.source.resource.id', $readyText->public_id)
            ->assertJsonPath('data.state', 'queued');
        Queue::assertPushed(ProcessIntakeItem::class, 1);

        // PDF (text layer) and images (OCR) are now extractable and accepted.
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $readyPdf->public_id])
            ->assertCreated()
            ->assertJsonPath('data.state', 'queued');
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $readyImage->public_id])
            ->assertCreated()
            ->assertJsonPath('data.state', 'queued');

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $pendingFile->public_id])
            ->assertUnprocessable();
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $linkResource->public_id])
            ->assertUnprocessable();
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $foreign->public_id])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_cancel_is_idempotent_and_terminal_states_conflict(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $queued = IntakeItem::factory()->queued()->create(['user_id' => $user->getKey()]);
        $extracted = IntakeItem::factory()->extracted()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$queued->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.state', 'cancelled');
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$queued->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.state', 'cancelled');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$extracted->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.state', 'cancelled');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$queued->public_id}/retry")
            ->assertStatus(409);
    }

    public function test_retry_requeues_only_retryable_failures_within_the_attempt_budget(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $failed = IntakeItem::factory()->failedRetryable()->create(['user_id' => $user->getKey()]);
        $exhausted = IntakeItem::factory()->failedRetryable(IntakeFailureCode::LinkHttpServerError)->create([
            'user_id' => $user->getKey(),
            'attempts' => (int) config('intake.max_attempts'),
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$failed->public_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.state', 'queued')
            ->assertJsonPath('data.failure_code', null);
        Queue::assertPushed(ProcessIntakeItem::class, 1);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$exhausted->public_id}/retry")
            ->assertStatus(409);
    }

    public function test_intake_lists_are_private_filterable_and_conceal_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $queued = IntakeItem::factory()->queued()->create(['user_id' => $user->getKey()]);
        IntakeItem::factory()->extracted()->create(['user_id' => $user->getKey()]);
        $foreign = IntakeItem::factory()->queued()->create(['user_id' => $other->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/intake')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/intake?state=queued')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $queued->public_id);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/intake/{$foreign->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/intake?state=unknown&per_page=51&unexpected=1')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => [
                'state',
                'per_page',
                'unexpected',
            ]]]]);
    }
}
