<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;
use App\Support\ApiErrorCode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Tests\Concerns\AssertsApiResponses;
use Tests\TestCase;

final class ResourceDirectoryTest extends TestCase
{
    use AssertsApiResponses;
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

    public function test_directories_list_active_courses_archived_with_content_and_the_unfiled_bucket(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $algorithms = Course::factory()->create(['user_id' => $owner->getKey(), 'title' => 'Algorithms']);
        $biology = Course::factory()->create(['user_id' => $owner->getKey(), 'title' => 'Biology']);
        $archivedWithContent = Course::factory()->archived()->create([
            'user_id' => $owner->getKey(),
            'title' => 'Retired Chemistry',
        ]);
        $archivedEmpty = Course::factory()->archived()->create([
            'user_id' => $owner->getKey(),
            'title' => 'Retired Drama',
        ]);
        Course::factory()->create(['user_id' => $other->getKey(), 'title' => 'Foreign Course']);

        Resource::factory()->count(2)->link()->forCourse($algorithms)->create();
        Resource::factory()->link()->forCourse($archivedWithContent)->create();
        Resource::factory()->count(3)->link()->create(['user_id' => $owner->getKey(), 'course_id' => null]);
        Resource::factory()->link()->create(['user_id' => $other->getKey(), 'course_id' => null]);

        $this->actingAs($owner, 'web');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources/directories')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $courseTitles = array_map(
            static fn (array $entry): ?string => $entry['course']['title'] ?? null,
            (array) $response->json('data'),
        );
        self::assertSame(
            ['Algorithms', 'Biology', 'Retired Chemistry', null],
            $courseTitles,
            'Active courses sort before archived ones, and unfiled is always last.',
        );

        $response
            ->assertJsonPath('data.0.kind', 'course')
            ->assertJsonPath('data.0.course.id', $algorithms->public_id)
            ->assertJsonPath('data.0.course.archive_status', 'active')
            ->assertJsonPath('data.0.resource_count', 2)
            ->assertJsonPath('data.1.course.id', $biology->public_id)
            ->assertJsonPath('data.1.resource_count', 0)
            ->assertJsonPath('data.2.course.id', $archivedWithContent->public_id)
            ->assertJsonPath('data.2.course.archive_status', 'archived')
            ->assertJsonPath('data.2.resource_count', 1)
            ->assertJsonPath('data.3.kind', 'unfiled')
            ->assertJsonPath('data.3.course', null)
            ->assertJsonPath('data.3.resource_count', 3);

        $payload = json_encode($response->json('data'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($archivedEmpty->public_id, $payload);
        self::assertStringNotContainsString('Foreign Course', $payload);
    }

    public function test_directories_reject_unknown_query_fields(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson('/api/v1/resources/directories?course_id=none'),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_directories_require_authentication(): void
    {
        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson('/api/v1/resources/directories'),
            401,
            ApiErrorCode::AuthenticationRequired,
        );
    }

    public function test_unfiled_sentinel_returns_only_resources_without_a_course(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        Resource::factory()->link()->forCourse($course)->create(['title' => 'Filed reference']);
        $unfiled = Resource::factory()->link()->create([
            'user_id' => $owner->getKey(),
            'course_id' => null,
            'title' => 'Unfiled reference',
        ]);
        Resource::factory()->link()->create([
            'user_id' => $other->getKey(),
            'course_id' => null,
            'title' => 'Foreign unfiled reference',
        ]);

        $this->actingAs($owner, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?course_id=none')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unfiled->public_id)
            ->assertJsonPath('data.0.course', null);
    }

    public function test_unknown_course_sentinel_values_are_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'web');

        $this->assertApiError(
            $this->withHeaders($this->headers())->getJson('/api/v1/resources?course_id=unfiled'),
            422,
            ApiErrorCode::ValidationFailed,
        );
    }

    public function test_title_sort_paginates_with_a_title_bearing_cursor_and_rejects_tampering(): void
    {
        $owner = User::factory()->create();
        $timestamp = CarbonImmutable::parse('2026-07-15T08:00:00Z');
        Resource::factory()->link()->create([
            'user_id' => $owner->getKey(),
            'course_id' => null,
            'title' => 'Alpha guide',
            'updated_at' => $timestamp,
        ]);
        Resource::factory()->link()->create([
            'user_id' => $owner->getKey(),
            'course_id' => null,
            'title' => 'Beta guide',
            'updated_at' => $timestamp,
        ]);

        $this->actingAs($owner, 'web');

        $firstPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?course_id=none&sort=title&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Alpha guide');

        $cursor = $firstPage->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);
        $decoded = Cursor::fromEncoded($cursor);
        self::assertInstanceOf(Cursor::class, $decoded);
        self::assertSame(
            ['resource_title_asc', 'public_id', '_pointsToNextItems'],
            array_keys($decoded->toArray()),
        );

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?course_id=none&sort=title&per_page=1&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Beta guide');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?course_id=none&sort=-updated_at&cursor='.rawurlencode($cursor))
            ->assertUnprocessable();

        $tampered = (new Cursor([
            'resource_title_asc' => "Alpha\x00guide",
            'public_id' => '01jz7n6h1c8j3r4s5t6v7w8x9y',
        ], true))->encode();

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?course_id=none&sort=title&cursor='.rawurlencode($tampered))
            ->assertUnprocessable();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'resource-directory-test-token',
        ];
    }
}
