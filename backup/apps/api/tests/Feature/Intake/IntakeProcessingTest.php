<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;

final class IntakeProcessingTest extends TestCase
{
    use InteractsWithIntake;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        $this->fakeDns();
    }

    public function test_link_processing_extracts_bounded_text_and_is_idempotent(): void
    {
        Http::fake([
            'https://university.example.edu/syllabus' => Http::response(
                '<html><head><title>Syllabus</title><script>alert("ignore")</script></head>'
                .'<body><h1>Research Methods</h1><p>Assignment due 2026-08-01.</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);
        $item = IntakeItem::factory()->queued()->create([
            'url' => 'https://university.example.edu/syllabus',
        ]);

        $this->runJob($item);

        $item->refresh();
        // The sync test queue chains classification, so the observable end
        // state of a successful run is awaiting_review with proposals ready.
        self::assertSame('awaiting_review', $item->state->value);
        self::assertSame(1, $item->attempts);
        self::assertNull($item->failure_code);
        self::assertGreaterThan(0, $item->suggestions()->count());

        $extracted = $item->artifacts()->where('kind', IntakeArtifactKind::ExtractedText->value)->firstOrFail();
        self::assertStringContainsString('Research Methods', (string) $extracted->text_content);
        self::assertStringContainsString('2026-08-01', (string) $extracted->text_content);
        self::assertStringNotContainsString('<h1>', (string) $extracted->text_content);
        self::assertStringNotContainsString('alert(', (string) $extracted->text_content);
        self::assertSame(2, $item->artifacts()->count());

        // Re-running the job against a finished item is a strict no-op.
        $this->runJob($item);
        $item->refresh();
        self::assertSame('awaiting_review', $item->state->value);
        self::assertSame(1, $item->attempts);
        self::assertSame(2, $item->artifacts()->count());
    }

    public function test_redirects_are_revalidated_and_private_hops_fail_final(): void
    {
        $this->fakeDns([
            'internal.example.edu' => ['10.1.2.3'],
        ]);
        Http::fake([
            'https://university.example.edu/start' => Http::response('', 302, [
                'Location' => 'https://internal.example.edu/secret',
            ]),
        ]);
        $item = IntakeItem::factory()->queued()->create([
            'url' => 'https://university.example.edu/start',
        ]);

        $this->runJob($item);

        $item->refresh();
        self::assertSame('failed_final', $item->state->value);
        self::assertSame('unsafe_url', $item->failure_code?->value);
    }

    public function test_http_client_errors_fail_final_and_server_errors_stay_retryable(): void
    {
        Http::fake([
            'https://university.example.edu/missing' => Http::response('gone', 404),
            'https://university.example.edu/flaky' => Http::response('boom', 503),
        ]);
        $missing = IntakeItem::factory()->queued()->create(['url' => 'https://university.example.edu/missing']);
        $flaky = IntakeItem::factory()->queued()->create(['url' => 'https://university.example.edu/flaky']);

        $this->runJob($missing);
        $this->runJob($flaky);

        self::assertSame('failed_final', $missing->refresh()->state->value);
        self::assertSame('link_http_client_error', $missing->failure_code?->value);
        self::assertSame('failed_retryable', $flaky->refresh()->state->value);
        self::assertSame('link_http_server_error', $flaky->failure_code?->value);
    }

    public function test_disallowed_content_types_and_oversized_bodies_fail_final(): void
    {
        Http::fake([
            'https://university.example.edu/archive.zip' => Http::response('PK', 200, [
                'Content-Type' => 'application/zip',
            ]),
            'https://university.example.edu/huge' => Http::response('x', 200, [
                'Content-Type' => 'text/plain',
                'Content-Length' => (string) (100 * 1024 * 1024),
            ]),
        ]);
        $zip = IntakeItem::factory()->queued()->create(['url' => 'https://university.example.edu/archive.zip']);
        $huge = IntakeItem::factory()->queued()->create(['url' => 'https://university.example.edu/huge']);

        $this->runJob($zip);
        $this->runJob($huge);

        self::assertSame('failed_final', $zip->refresh()->state->value);
        self::assertSame('unsupported_content_type', $zip->failure_code?->value);
        self::assertSame('failed_final', $huge->refresh()->state->value);
        self::assertSame('content_too_large', $huge->failure_code?->value);
    }

    public function test_retryable_failures_recover_through_the_full_retry_cycle(): void
    {
        Http::fakeSequence('https://university.example.edu/notes')
            ->push('down', 500)
            ->push("Week one notes.\nRead chapters one and two.", 200, ['Content-Type' => 'text/plain']);
        $user = User::factory()->create();
        $item = IntakeItem::factory()->queued()->create([
            'user_id' => $user->getKey(),
            'url' => 'https://university.example.edu/notes',
        ]);

        $this->runJob($item);
        self::assertSame('failed_retryable', $item->refresh()->state->value);

        $this->actingAs($user, 'web');
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/intake/{$item->public_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.state', 'queued');

        $this->runJob($item);
        $item->refresh();
        self::assertSame('awaiting_review', $item->state->value);
        self::assertSame(2, $item->attempts);
        self::assertSame(2, $item->artifacts()->count());
    }

    public function test_file_processing_reads_the_private_object_within_bounds(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        $storedFile = StoredFile::factory()->forResource($resource)->state(['declared_mime_type' => 'text/plain'])->ready()->create();
        Storage::disk('s3')->put((string) $storedFile->object_key, "Lecture transcript.\nDiscussion of study design.");

        $item = IntakeItem::factory()->queued()->create([
            'user_id' => $user->getKey(),
            'source_type' => 'file',
            'resource_id' => $resource->getKey(),
            'url' => null,
        ]);

        $this->runJob($item);

        $item->refresh();
        self::assertSame('awaiting_review', $item->state->value);
        $extracted = $item->artifacts()->where('kind', IntakeArtifactKind::ExtractedText->value)->firstOrFail();
        self::assertStringContainsString('Lecture transcript.', (string) $extracted->text_content);

        $acquired = $item->artifacts()->where('kind', IntakeArtifactKind::AcquiredContent->value)->firstOrFail();
        self::assertNull($acquired->text_content);
    }

    public function test_missing_stored_objects_fail_retryable(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $user->getKey()]);
        StoredFile::factory()->forResource($resource)->state(['declared_mime_type' => 'text/plain'])->ready()->create();

        $item = IntakeItem::factory()->queued()->create([
            'user_id' => $user->getKey(),
            'source_type' => 'file',
            'resource_id' => $resource->getKey(),
            'url' => null,
        ]);

        $this->runJob($item);

        $item->refresh();
        self::assertSame('failed_retryable', $item->state->value);
        self::assertSame('file_unavailable', $item->failure_code?->value);
    }

    private function runJob(IntakeItem $item): void
    {
        $this->app->call([new ProcessIntakeItem((int) $item->getKey()), 'handle']);
    }
}
