<?php

declare(strict_types=1);

namespace Tests\Feature\Courses;

use App\Domains\Courses\Models\Course;
use App\Domains\Users\Models\User;
use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class CourseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }

    public function test_term_and_course_lifecycle_is_versioned_idempotent_and_explicitly_destructive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $termResponse = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/academic-terms', [
                'label' => '  Fall 2026  ',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-20',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.label', 'Fall 2026')
            ->assertJsonPath('data.course_counts.active', 0)
            ->assertJsonPath('data.course_counts.archived', 0)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.public_id');
        $termId = $termResponse->json('data.id');
        $this->assertIsString($termId);

        $updatedTerm = $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/academic-terms/{$termId}", [
                'expected_version' => 1,
                'label' => 'Fall 2026 — updated',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.label', 'Fall 2026 — updated');
        $termUpdatedAt = $updatedTerm->json('data.updated_at');

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/academic-terms/{$termId}", [
                'expected_version' => 1,
                'label' => 'Fall 2026 — updated',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $termUpdatedAt);

        $courseResponse = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/courses', [
                'title' => '  Distributed Systems  ',
                'code' => ' CSE 4201 ',
                'description' => "Consensus and replication.\nWeekly lab.",
                'term_id' => $termId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.title', 'Distributed Systems')
            ->assertJsonPath('data.code', 'CSE 4201')
            ->assertJsonPath('data.term.id', $termId)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.archived_at', null)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.academic_term_id')
            ->assertJsonMissingPath('data.onboarding_position');
        $courseId = $courseResponse->json('data.id');
        $this->assertIsString($courseId);

        $this->withHeaders($this->readHeaders())
            ->getJson("/api/v1/academic-terms/{$termId}")
            ->assertOk()
            ->assertJsonPath('data.course_counts.active', 1)
            ->assertJsonPath('data.course_counts.archived', 0);

        $updatedCourse = $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}", [
                'expected_version' => 1,
                'title' => 'Advanced Distributed Systems',
                'code' => 'CSE 4201',
                'description' => null,
                'term_id' => $termId,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.title', 'Advanced Distributed Systems');
        $courseUpdatedAt = $updatedCourse->json('data.updated_at');

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}", [
                'expected_version' => 1,
                'title' => 'Advanced Distributed Systems',
                'code' => 'CSE 4201',
                'description' => null,
                'term_id' => $termId,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_at', $courseUpdatedAt);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}", [
                'expected_version' => 1,
                'title' => 'A stale overwrite',
                'code' => null,
                'description' => null,
                'term_id' => null,
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $archived = $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}/archive", ['expected_version' => 2])
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.status', 'archived');
        $archivedAt = $archived->json('data.archived_at');
        $this->assertIsString($archivedAt);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/courses/{$courseId}/archive", ['expected_version' => 2])
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.archived_at', $archivedAt);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/academic-terms/{$termId}", ['expected_version' => 2])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$courseId}/archive", ['expected_version' => 3])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.archived_at', null);

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$courseId}/archive", ['expected_version' => 3])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.status', 'active');

        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$courseId}", ['expected_version' => 3])
            ->assertConflict();

        $courseDelete = $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$courseId}", ['expected_version' => 4])
            ->assertNoContent();
        $this->assertMatchesRegularExpression(
            RequestId::PATTERN,
            (string) $courseDelete->headers->get(RequestId::HEADER),
        );
        $this->assertDatabaseMissing('courses', ['public_id' => $courseId]);

        $termDelete = $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/academic-terms/{$termId}", ['expected_version' => 2])
            ->assertNoContent();
        $this->assertMatchesRegularExpression(
            RequestId::PATTERN,
            (string) $termDelete->headers->get(RequestId::HEADER),
        );
        $this->assertDatabaseMissing('academic_terms', ['public_id' => $termId]);
    }

    public function test_academic_inputs_are_strict_normalized_and_allow_independent_term_dates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $term = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/academic-terms', [
                'label' => 'Open-ended term',
                'starts_on' => '2026-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.starts_on', '2026-01-01')
            ->assertJsonPath('data.ends_on', null);
        $termId = $term->json('data.id');
        $this->assertIsString($termId);

        $this->withHeaders($this->mutationHeaders())
            ->putJson("/api/v1/academic-terms/{$termId}", [
                'expected_version' => 1,
                'label' => 'End-date-only term',
                'ends_on' => '2026-12-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.starts_on', null)
            ->assertJsonPath('data.ends_on', '2026-12-31');

        $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/academic-terms', [
                'label' => 'Invalid term',
                'starts_on' => '2026-12-31',
                'ends_on' => '2026-01-01',
                'user_id' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['ends_on', 'user_id']]]]);

        $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/courses', [
                'title' => '<script>private title</script>',
                'code' => '   ',
                'description' => null,
                'term_id' => 'not-a-public-id',
                'archived_at' => '2026-01-01T00:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['title', 'term_id', 'archived_at']]],
            ]);

        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $this->withHeaders($this->mutationHeaders())
            ->deleteJson("/api/v1/courses/{$course->public_id}", [
                'expected_version' => 1,
                'force' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['force']]]]);

        $this->withHeaders($this->readHeaders())
            ->getJson('/api/v1/courses?cursor%5Bnested%5D=value')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_academic_database_failures_do_not_disclose_private_values(): void
    {
        $privateValue = 'private course title that must never reach logs';
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        DB::statement(<<<'SQL'
            ALTER TABLE courses
            ADD CONSTRAINT courses_synthetic_private_failure
            CHECK (title <> 'private course title that must never reach logs')
            SQL);
        Log::spy();

        $response = $this->withHeaders($this->mutationHeaders())
            ->postJson('/api/v1/courses', [
                'title' => $privateValue,
                'code' => null,
                'description' => null,
                'term_id' => null,
            ])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertStringNotContainsString($privateValue, (string) $response->getContent());
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Academic persistence failed.', Mockery::on(
                static fn (array $context): bool => $context['operation'] === 'course.create'
                    && $context['sql_state'] === '23514'
                    && $context['exception_type'] === QueryException::class
                    && is_string($context['request_id'])
                    && ! str_contains(serialize($context), $privateValue),
            ));
    }

    /** @return array<string, string> */
    private function mutationHeaders(): array
    {
        return [
            ...$this->readHeaders(),
            'X-XSRF-TOKEN' => 'course-lifecycle-test-token',
        ];
    }

    /** @return array<string, string> */
    private function readHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
