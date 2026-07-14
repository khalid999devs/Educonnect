<?php

declare(strict_types=1);

namespace Tests\Feature\Courses;

use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Tests\TestCase;

final class CourseListTest extends TestCase
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

    public function test_course_lists_are_owner_scoped_filterable_summarized_and_cursor_stable(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $timestamp = CarbonImmutable::parse('2026-07-14T08:30:00Z');
        $termA = AcademicTerm::factory()->create([
            'user_id' => $owner->getKey(),
            'label' => 'Fall % Special',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $termB = AcademicTerm::factory()->create([
            'user_id' => $owner->getKey(),
            'label' => 'Fall Standard',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $foreignTerm = AcademicTerm::factory()->create(['user_id' => $other->getKey()]);

        $activeCourses = collect([
            Course::factory()->forAcademicTerm($termA)->create([
                'title' => 'Alpha % Lab',
                'code' => 'LAB 101',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
            Course::factory()->forAcademicTerm($termA)->create([
                'title' => 'Alpha General',
                'code' => 'CSE 101',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
            Course::factory()->create([
                'user_id' => $owner->getKey(),
                'title' => 'Gamma Seminar',
                'code' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
        ]);
        $archived = Course::factory()->forAcademicTerm($termB)->archived()->create([
            'title' => 'Beta Systems',
            'code' => 'CSE 202',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        Course::factory()->forAcademicTerm($foreignTerm)->create([
            'title' => 'Other user private course',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->actingAs($owner, 'web');
        $expectedActiveIds = $activeCourses->pluck('public_id')->sort()->values()->all();
        $firstPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?sort=updated_at&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.summary.total', 4)
            ->assertJsonPath('meta.summary.active', 3)
            ->assertJsonPath('meta.summary.archived', 1)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.onboarding_position');
        $firstIds = $firstPage->json('data.*.id');
        $this->assertSame(array_slice($expectedActiveIds, 0, 2), $firstIds);
        $cursor = $firstPage->json('meta.pagination.next_cursor');
        $this->assertIsString($cursor);
        $this->assertIsString($firstPage->json('links.next'));

        $decoded = Cursor::fromEncoded($cursor);
        $this->assertInstanceOf(Cursor::class, $decoded);
        $cursorValues = $decoded->toArray();
        $this->assertSame(['cursor_updated_at_asc', 'public_id', '_pointsToNextItems'], array_keys($cursorValues));
        $encodedCursorValues = json_encode($cursorValues, JSON_THROW_ON_ERROR);
        foreach (['Alpha % Lab', 'CSE 101', 'Fall % Special', 'user_id', 'academic_term_id'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encodedCursorValues);
        }

        $secondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?sort=updated_at&per_page=2&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.next_cursor', null);
        $this->assertSame(array_slice($expectedActiveIds, 2), $secondPage->json('data.*.id'));
        $this->assertIsString($secondPage->json('meta.pagination.previous_cursor'));
        $this->assertSame(
            $expectedActiveIds,
            [...$firstIds, ...$secondPage->json('data.*.id')],
        );

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?status=all&per_page=50&sort=updated_at')
            ->assertOk()
            ->assertJsonCount(4, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?status=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->public_id)
            ->assertJsonPath('data.0.status', 'archived');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?term_id='.$termA->public_id.'&per_page=50')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?status=all&search='.rawurlencode('cse 2'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->public_id);
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?status=all&search='.rawurlencode('alpha %'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Alpha % Lab');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?sort=created_at&cursor='.rawurlencode($cursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?sort=-updated_at&cursor='.rawurlencode($cursor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $forgedTimestamp = (new Cursor([
            'cursor_updated_at_asc' => 'next Thursday',
            'public_id' => $expectedActiveIds[0],
        ]))->encode();
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?sort=updated_at&cursor='.rawurlencode($forgedTimestamp))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/courses?term_id='.$foreignTerm->public_id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_term_lists_include_per_term_counts_literal_prefix_search_and_private_cursors(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $timestamp = CarbonImmutable::parse('2026-07-14T09:00:00Z');
        $literal = AcademicTerm::factory()->create([
            'user_id' => $owner->getKey(),
            'label' => 'Term % Private',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $ordinary = AcademicTerm::factory()->create([
            'user_id' => $owner->getKey(),
            'label' => 'Term Ordinary',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        Course::factory()->forAcademicTerm($literal)->create();
        Course::factory()->forAcademicTerm($literal)->archived()->create();
        Course::factory()->forAcademicTerm($ordinary)->archived()->create();
        AcademicTerm::factory()->create(['user_id' => $other->getKey(), 'label' => 'Term % Foreign']);

        $this->actingAs($owner, 'web');
        $search = $this->withHeaders($this->headers())
            ->getJson('/api/v1/academic-terms?search='.rawurlencode('term %'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->public_id)
            ->assertJsonPath('data.0.course_counts.active', 1)
            ->assertJsonPath('data.0.course_counts.archived', 1);
        $this->assertNull($search->json('meta.pagination.next_cursor'));

        $firstPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/academic-terms?sort=updated_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $cursor = $firstPage->json('meta.pagination.next_cursor');
        $this->assertIsString($cursor);
        $decoded = Cursor::fromEncoded($cursor);
        $this->assertInstanceOf(Cursor::class, $decoded);
        $cursorJson = json_encode($decoded->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Term % Private', $cursorJson);
        $this->assertStringNotContainsString('Term Ordinary', $cursorJson);

        $secondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/academic-terms?sort=updated_at&per_page=1&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $listedIds = [$firstPage->json('data.0.id'), $secondPage->json('data.0.id')];
        sort($listedIds);
        $expectedIds = [$literal->public_id, $ordinary->public_id];
        sort($expectedIds);
        $this->assertSame($expectedIds, $listedIds);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }
}
