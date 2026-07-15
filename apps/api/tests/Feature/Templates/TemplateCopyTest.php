<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Courses\Models\Course;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Templates\Concerns\InteractsWithTemplates;
use Tests\TestCase;

final class TemplateCopyTest extends TestCase
{
    use InteractsWithTemplates;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_a_user_copies_a_published_template_without_mutating_the_source(): void
    {
        $user = User::factory()->create();
        $template = $this->publishedTemplate([
            'title' => 'Weekly Study Plan',
        ], [
            'body' => 'Original immutable body.',
            'version_number' => 1,
        ]);
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'dashboard'])
            ->assertCreated()
            ->assertJsonPath('data.destination', 'dashboard')
            ->assertJsonPath('data.course', null)
            ->assertJsonPath('data.source.template_id', $template->public_id)
            ->assertJsonPath('data.source.version_number', 1)
            ->assertJsonPath('data.title', 'Weekly Study Plan')
            ->assertJsonPath('data.body', 'Original immutable body.')
            ->assertJsonPath('data.version', 1);
        $copyId = $created->json('data.id');

        $repeat = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'dashboard'])
            ->assertOk();
        self::assertSame($copyId, $repeat->json('data.id'));
        self::assertSame(1, UserTemplateCopy::query()->where('user_id', $user->getKey())->count());

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$copyId}", [
                'expected_version' => 1,
                'title' => 'My Adjusted Plan',
                'body' => 'My own edited body.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'My Adjusted Plan')
            ->assertJsonPath('data.body', 'My own edited body.')
            ->assertJsonPath('data.version', 2);

        $source = TemplateVersion::query()->where('template_id', $template->getKey())->firstOrFail();
        self::assertSame('Original immutable body.', $source->body);
        self::assertSame('Weekly Study Plan', $template->refresh()->title);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/templates/{$template->public_id}")
            ->assertOk()
            ->assertJsonPath('data.latest_version.body', 'Original immutable body.')
            ->assertJsonPath('data.viewer_state.active_copy_count', 1);
    }

    public function test_course_destination_requires_an_owned_active_course(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $template = $this->publishedTemplate();
        $ownCourse = Course::factory()->create(['user_id' => $user->getKey()]);
        $foreignCourse = Course::factory()->create(['user_id' => $other->getKey()]);
        $archivedCourse = Course::factory()->create([
            'user_id' => $user->getKey(),
            'archived_at' => now(),
        ]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'course',
                'course_id' => $ownCourse->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.destination', 'course')
            ->assertJsonPath('data.course.id', $ownCourse->public_id);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'course',
                'course_id' => $foreignCourse->public_id,
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'course',
                'course_id' => $archivedCourse->public_id,
            ])
            ->assertStatus(409);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'course'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['course_id']]]]);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'dashboard',
                'course_id' => $ownCourse->public_id,
            ])
            ->assertUnprocessable();

        // A dashboard copy and a course copy of the same template can coexist.
        $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'dashboard'])
            ->assertCreated();
        self::assertSame(2, UserTemplateCopy::query()->where('user_id', $user->getKey())->count());
    }

    public function test_copy_update_enforces_versioning_archive_state_and_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $template = $this->publishedTemplate();
        $this->actingAs($user, 'web');

        $copyId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'dashboard'])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$copyId}", [
                'expected_version' => 9,
                'title' => 'Stale Update',
                'body' => 'Stale body.',
            ])
            ->assertStatus(409);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$copyId}/archive", ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.version', 2);
        self::assertNotNull($this->withHeaders($this->headers())
            ->getJson("/api/v1/template-copies/{$copyId}")
            ->assertOk()
            ->json('data.archived_at'));

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$copyId}", [
                'expected_version' => 2,
                'title' => 'Archived Edit',
                'body' => 'Edit after archive.',
            ])
            ->assertStatus(409);

        // Archived copies free the idempotency slot for a fresh copy.
        $freshId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'dashboard'])
            ->assertCreated()
            ->json('data.id');
        self::assertNotSame($copyId, $freshId);

        // Restoring the archived copy would collide with the fresh active one.
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/template-copies/{$copyId}/archive", ['expected_version' => 2])
            ->assertStatus(409);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$freshId}/archive", ['expected_version' => 1])
            ->assertOk();
        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/template-copies/{$copyId}/archive", ['expected_version' => 2])
            ->assertOk()
            ->assertJsonPath('data.archived_at', null)
            ->assertJsonPath('data.version', 3);

        // Another user's copy is concealed with the standard 404.
        $foreignCopy = UserTemplateCopy::factory()->create(['user_id' => $other->getKey()]);
        $this->withHeaders($this->headers())
            ->getJson("/api/v1/template-copies/{$foreignCopy->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_copy_list_supports_destination_course_and_archived_filters(): void
    {
        $user = User::factory()->create();
        $template = $this->publishedTemplate(['title' => 'Filterable Template']);
        $course = Course::factory()->create(['user_id' => $user->getKey()]);
        $this->actingAs($user, 'web');

        $dashboardId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", ['destination' => 'dashboard'])
            ->assertCreated()
            ->json('data.id');
        $courseId = $this->withHeaders($this->headers())
            ->postJson("/api/v1/templates/{$template->public_id}/copies", [
                'destination' => 'course',
                'course_id' => $course->public_id,
            ])
            ->assertCreated()
            ->json('data.id');
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/template-copies/{$dashboardId}/archive", ['expected_version' => 1])
            ->assertOk();

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/template-copies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $courseId);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/template-copies?include_archived=1&sort=title')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/template-copies?destination=course&course={$course->public_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $courseId);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/template-copies?destination=unknown&unexpected=1')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['destination', 'unexpected']]]]);
    }
}
