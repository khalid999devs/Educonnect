<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Courses\Models\Course;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Tests\TestCase;

final class ResourceQueryTest extends TestCase
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

    public function test_resource_list_is_owner_scoped_filterable_and_uses_private_stable_cursors(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['user_id' => $owner->getKey()]);
        $timestamp = CarbonImmutable::parse('2026-07-15T08:00:00Z');
        $first = Resource::factory()->link('https://example.edu/alpha')->forCourse($course)->forTopic('Algorithms')->create([
            'title' => 'Private Alpha Reference',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $second = Resource::factory()->link('https://example.edu/beta')->forCourse($course)->forTopic('Algorithms')->create([
            'title' => 'Private Beta Reference',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        Resource::factory()->link()->create([
            'user_id' => $other->getKey(),
            'title' => 'Private foreign reference',
            'updated_at' => $timestamp,
        ]);
        $ready = Resource::factory()->file()->forCourse($course)->forTopic('Lecture notes')->create([
            'title' => 'Ready private file',
            'updated_at' => $timestamp->addMinute(),
        ]);
        StoredFile::factory()->forResource($ready)->ready()->create();
        $deleting = Resource::factory()->file()->forCourse($course)->create([
            'title' => 'Deleting private file',
            'updated_at' => $timestamp->addMinutes(2),
        ]);
        StoredFile::factory()->forResource($deleting)->deletionPending()->create();
        $this->actingAs($owner, 'web');

        $firstPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?search=Private&kind=link&course_id='.$course->public_id
                .'&topic=Algorithms&sort=updated_at&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $cursor = $firstPage->json('meta.pagination.next_cursor');
        self::assertIsString($cursor);
        $decoded = Cursor::fromEncoded($cursor);
        self::assertInstanceOf(Cursor::class, $decoded);
        self::assertSame(
            ['resource_updated_at_asc', 'public_id', '_pointsToNextItems'],
            array_keys($decoded->toArray()),
        );
        $cursorJson = json_encode($decoded->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Private Alpha Reference', $cursorJson);
        self::assertStringNotContainsString('https://example.edu', $cursorJson);

        $secondPage = $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?search=Private&kind=link&course_id='.$course->public_id
                .'&topic=Algorithms&sort=updated_at&per_page=1&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $listedIds = [$firstPage->json('data.0.id'), $secondPage->json('data.0.id')];
        sort($listedIds);
        $expectedIds = [$first->public_id, $second->public_id];
        sort($expectedIds);
        self::assertSame($expectedIds, $listedIds);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?kind=file&file_status=ready&topic=Lecture%20notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ready->public_id)
            ->assertJsonPath('data.0.file.status', 'ready')
            ->assertJsonMissingPath('data.0.file.sha256')
            ->assertJsonMissingPath('data.0.file.object_key')
            ->assertJsonMissingPath('data.0.file.upload_key')
            ->assertJsonMissingPath('data.0.file.disk');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?kind=file&file_status=deletion_pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deleting->public_id);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?sort=-updated_at&cursor='.rawurlencode($cursor))
            ->assertUnprocessable();
    }

    public function test_title_search_treats_sql_wildcards_as_literal_prefix_characters(): void
    {
        $owner = User::factory()->create();
        Resource::factory()->create(['user_id' => $owner->getKey(), 'title' => '100%_Study guide']);
        Resource::factory()->create(['user_id' => $owner->getKey(), 'title' => '100x Study guide']);
        $this->actingAs($owner, 'web');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/resources?search='.rawurlencode('100%_'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '100%_Study guide');
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'resource-query-test-token',
        ];
    }
}
