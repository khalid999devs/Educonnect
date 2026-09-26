<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Models\Role;
use App\Domains\Courses\Models\Course;
use App\Domains\Guidance\Enums\GuidanceReviewState;
use App\Domains\Planner\Models\Task;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Templates\Enums\TemplateBadge;
use App\Domains\Templates\Enums\TemplateFormat;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Local demo/seed data so a fresh install has known sign-in accounts and a
 * populated catalog + resources. NOT registered in DatabaseSeeder so it never
 * runs during tests/CI. Every step is idempotent and additive: running it
 * twice creates nothing new and deletes nothing.
 *
 * Run with: php artisan db:seed --class=DemoSeeder --force
 */
final class DemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Password123!';

    private const REVIEWED_AT = '2026-07-01T09:00:00Z';

    public function run(): void
    {
        // 1) Guidance catalog (tools / prompts / workflows). Idempotent itself.
        $this->call(GuidanceCatalogSeeder::class);

        // 2) Published templates.
        $this->seedTemplates();

        // 3) Known accounts.
        $admin = $this->ensureUser('Demo Admin', 'admin@educonnect.test', RoleKey::SuperAdmin);
        $student = $this->ensureUser('Demo Student', 'student@educonnect.test', RoleKey::Student);

        // 4) Student-owned resources (link + downloadable file).
        $this->seedStudentResources($student);

        // 5) Optional: a small course/task workspace for dashboard + planner.
        $this->seedStudentWorkspace($student);

        // Optional: mark onboarding complete so the student lands on the dashboard.
        $onboardingResult = $this->completeOnboardingIfPossible($student);

        $this->command?->info('DemoSeeder finished. Onboarding: '.$onboardingResult);
    }

    // ---------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------

    private function seedTemplates(): void
    {
        $reviewedAt = Carbon::parse(self::REVIEWED_AT);

        $studyPlanning = $this->category('study-planning');
        $academicWriting = $this->category('academic-writing');

        $this->publishTemplate($studyPlanning, $reviewedAt,
            'Weekly Study Plan',
            'A reusable weekly grid you copy each week to timebox study across your courses before deadlines.',
            'Fill the plan in yourself and treat it as a schedule, not as completed work; do the studying it describes.',
            'EduConnect drafted and reviewed this template with neutral placeholder content and no vendor endorsements.',
            <<<'MD'
            # Weekly Study Plan

            **Week of:** _{{week_start}}_

            ## Goals for the week
            - [ ] {{goal_1}}
            - [ ] {{goal_2}}
            - [ ] {{goal_3}}

            ## Time blocks
            | Day | Course / topic | Focus block | Notes |
            | --- | -------------- | ----------- | ----- |
            | Mon | {{course}} | 25 min x 2 | |
            | Tue | {{course}} | 25 min x 2 | |
            | Wed | {{course}} | 25 min x 2 | |
            | Thu | {{course}} | 25 min x 2 | |
            | Fri | {{course}} | 25 min x 2 | |

            ## End-of-week review
            - What went to plan?
            - What slipped, and why?
            - One adjustment for next week: _{{adjustment}}_
            MD,
        );

        $this->publishTemplate($academicWriting, $reviewedAt,
            'Literature Review Outline',
            'A section-by-section scaffold for organising sources into a literature review you then write in your own words.',
            'Use the outline to structure your own analysis and cite every source; do not present summarised sources as your own findings.',
            'EduConnect drafted and reviewed this template against common academic-writing guidance.',
            <<<'MD'
            # Literature Review Outline

            **Topic / question:** _{{research_question}}_

            ## 1. Introduction
            - Scope of the review
            - Why this topic matters

            ## 2. Themes
            ### Theme A: {{theme_a}}
            - Source 1: key claim, method, limitation
            - Source 2: key claim, method, limitation
            - Where they agree / disagree

            ### Theme B: {{theme_b}}
            - Source 1: key claim, method, limitation
            - Source 2: key claim, method, limitation

            ## 3. Gaps and open questions
            - What is missing across the sources?

            ## 4. Synthesis
            - How the themes connect to your question

            ## References
            1. {{citation_1}}
            2. {{citation_2}}
            MD,
        );

        $this->publishTemplate($academicWriting, $reviewedAt,
            'Lab Report Skeleton',
            'A standard lab-report structure you copy per experiment so every write-up covers method, results, and analysis consistently.',
            'Record your own observations and analysis; copying results you did not measure is an integrity violation.',
            'EduConnect drafted and reviewed this template with neutral placeholder content.',
            <<<'MD'
            # Lab Report: {{experiment_title}}

            **Course:** {{course}}  **Date:** {{date}}

            ## Aim
            State the objective of the experiment in one sentence.

            ## Hypothesis
            _{{hypothesis}}_

            ## Materials and method
            1. Step one
            2. Step two
            3. Step three

            ## Results
            | Trial | Measurement | Units |
            | ----- | ----------- | ----- |
            | 1 | | |
            | 2 | | |
            | 3 | | |

            ## Analysis
            - What do the results show?
            - Sources of error and their likely effect

            ## Conclusion
            Relate the results back to the aim and hypothesis.
            MD,
        );
    }

    private function publishTemplate(
        ToolCategory $category,
        Carbon $reviewedAt,
        string $title,
        string $summary,
        string $integrityNote,
        string $provenance,
        string $body,
    ): void {
        $title = trim($title);

        $template = Template::query()->where('title', $title)->first();

        if ($template instanceof Template && $template->state === GuidanceReviewState::Published) {
            return;
        }

        if (! $template instanceof Template) {
            // Insert as a draft first so the "versions only change in draft"
            // trigger and the "publication requires a version" trigger are both
            // satisfied. The state machine only allows draft -> in_review ->
            // published, so we walk it a step at a time below.
            $template = new Template;
            $template->forceFill([
                'tool_category_id' => $category->getKey(),
                'title' => $title,
                'summary' => trim($summary),
                'integrity_note' => trim($integrityNote),
                'provenance' => trim($provenance),
                'badge' => TemplateBadge::ApprovedFree->value,
                'state' => GuidanceReviewState::Draft->value,
                'last_reviewed_at' => null,
                'published_at' => null,
                'archived_at' => null,
                'version' => 1,
            ])->save();
        }

        // Ensure exactly one immutable version exists (added only while draft).
        if ($template->state === GuidanceReviewState::Draft && $template->versions()->count() === 0) {
            $version = new TemplateVersion;
            $version->forceFill([
                'template_id' => $template->getKey(),
                'version_number' => 1,
                'format' => TemplateFormat::Markdown->value,
                'body' => trim($body),
                'change_note' => null,
            ])->save();
        }

        if ($template->state === GuidanceReviewState::Draft) {
            $template->forceFill(['state' => GuidanceReviewState::InReview->value])->save();
        }

        if ($template->state === GuidanceReviewState::InReview) {
            $template->forceFill([
                'state' => GuidanceReviewState::Published->value,
                'last_reviewed_at' => $reviewedAt,
                'published_at' => $reviewedAt,
            ])->save();
        }
    }

    private function category(string $slug): ToolCategory
    {
        $category = ToolCategory::query()->where('slug', $slug)->first();

        if ($category instanceof ToolCategory) {
            return $category;
        }

        // Fallback: mirror GuidanceCatalogSeeder's category shape if it is absent.
        $category = new ToolCategory;
        $category->forceFill([
            'slug' => $slug,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'description' => 'Demo category.',
            'sort_order' => 90,
        ])->save();

        return $category;
    }

    // ---------------------------------------------------------------------
    // Accounts
    // ---------------------------------------------------------------------

    private function ensureUser(string $name, string $email, RoleKey $role): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ])->save();
        } else {
            // Refresh known demo credentials without duplicating the account.
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        $roleId = Role::query()->where('key', $role->value)->value('id');

        if ($roleId !== null && ! $user->roles()->where('roles.id', $roleId)->exists()) {
            $user->roles()->attach($roleId, ['assigned_at' => now()]);
        }

        return $user;
    }

    // ---------------------------------------------------------------------
    // Resources
    // ---------------------------------------------------------------------

    private function seedStudentResources(User $student): void
    {
        $links = [
            [
                'title' => 'MIT OpenCourseWare',
                'description' => 'Free lecture notes, assignments and exams across many subjects.',
                'topic_label' => 'Study planning',
                'source_url' => 'https://ocw.mit.edu/',
            ],
            [
                'title' => 'Purdue OWL Writing Lab',
                'description' => 'Reference for citation styles and academic writing structure.',
                'topic_label' => 'Academic writing',
                'source_url' => 'https://owl.purdue.edu/owl/purdue_owl.html',
            ],
            [
                'title' => 'Khan Academy',
                'description' => 'Practice exercises and short video explanations for core topics.',
                'topic_label' => 'Revision',
                'source_url' => 'https://www.khanacademy.org/',
            ],
        ];

        foreach ($links as $link) {
            if ($this->studentHasResource($student, $link['title'])) {
                continue;
            }

            $resource = new Resource;
            $resource->forceFill([
                'user_id' => $student->getKey(),
                'course_id' => null,
                'kind' => ResourceKind::Link->value,
                'title' => $link['title'],
                'description' => $link['description'],
                'topic_label' => $link['topic_label'],
                'source_url' => $link['source_url'],
                'version' => 1,
            ])->save();
        }

        $files = [
            [
                'title' => 'Weekly study plan (starter)',
                'topic_label' => 'Study planning',
                'original_name' => 'weekly-study-plan.md',
                'body' => <<<'MD'
                # Weekly Study Plan (starter)

                A copy of the planning template you can fill in each week.

                ## This week
                - [ ] Review lecture notes
                - [ ] Two focused 25-minute blocks per course
                - [ ] One past-paper question

                ## Reflection
                - What worked?
                - What to change next week?
                MD,
            ],
            [
                'title' => 'Citation checklist (starter)',
                'topic_label' => 'Academic writing',
                'original_name' => 'citation-checklist.md',
                'body' => <<<'MD'
                # Citation Checklist (starter)

                Work through this before submitting any written work.

                - [ ] Every quote has a page reference
                - [ ] Every source in the text appears in the reference list
                - [ ] Reference list is alphabetised and consistent
                - [ ] Author, year, title and venue are correct for each source
                MD,
            ],
        ];

        foreach ($files as $file) {
            if ($this->studentHasResource($student, $file['title'])) {
                continue;
            }

            $resource = new Resource;
            $resource->forceFill([
                'user_id' => $student->getKey(),
                'course_id' => null,
                'kind' => ResourceKind::File->value,
                'title' => $file['title'],
                'description' => 'A small downloadable starter document.',
                'topic_label' => $file['topic_label'],
                'source_url' => null,
                'version' => 1,
            ])->save();

            $bytes = trim($file['body'])."\n";
            $objectKey = 'resources/v1/objects/'.bin2hex(random_bytes(16));

            Storage::disk(config('resources.disk'))->put($objectKey, $bytes);

            $now = now();
            $storedFile = new StoredFile;
            $storedFile->forceFill([
                'resource_id' => $resource->getKey(),
                'user_id' => $student->getKey(),
                'original_name' => $file['original_name'],
                'declared_mime_type' => 'text/markdown',
                'expected_size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'upload_key' => null,
                'object_key' => $objectKey,
                'verified_mime_type' => 'text/markdown',
                'verified_size' => strlen($bytes),
                'status' => StoredFileStatus::Ready->value,
                'upload_expires_at' => $now->copy()->addMinutes(15),
                'cleanup_after' => null,
                'cleanup_started_at' => null,
                'cleanup_failures' => 0,
                'purge_ready_at' => null,
                'ready_at' => $now,
            ])->save();
        }
    }

    private function studentHasResource(User $student, string $title): bool
    {
        return Resource::query()
            ->where('user_id', $student->getKey())
            ->where('title', $title)
            ->exists();
    }

    // ---------------------------------------------------------------------
    // Courses + tasks (optional dashboard/planner data)
    // ---------------------------------------------------------------------

    private function seedStudentWorkspace(User $student): void
    {
        if (Course::query()->where('user_id', $student->getKey())->exists()) {
            return;
        }

        $dataStructures = Course::factory()->create([
            'user_id' => $student->getKey(),
            'title' => 'Data Structures',
            'code' => 'CS 201',
            'description' => 'Core data structures and algorithmic complexity.',
        ]);

        $writing = Course::factory()->create([
            'user_id' => $student->getKey(),
            'title' => 'Academic Writing',
            'code' => 'ENG 110',
            'description' => 'Structuring essays and citing sources.',
        ]);

        Task::factory()->forCourse($dataStructures)->dueAt(now()->addDays(3))->create([
            'title' => 'Finish problem set 3',
        ]);
        Task::factory()->forCourse($dataStructures)->inProgress()->create([
            'title' => 'Review lecture notes on trees',
        ]);
        Task::factory()->forCourse($writing)->dueAt(now()->addDays(7))->create([
            'title' => 'Draft literature review outline',
        ]);
        Task::factory()->completed(now()->subDay())->create([
            'user_id' => $student->getKey(),
            'course_id' => null,
            'title' => 'Set up a weekly study schedule',
        ]);
    }

    // ---------------------------------------------------------------------
    // Onboarding completion (best-effort, defensive)
    // ---------------------------------------------------------------------

    private function completeOnboardingIfPossible(User $student): string
    {
        $userId = $student->getKey();

        $existingCompletedAt = DB::table('onboarding_progress')
            ->where('user_id', $userId)
            ->value('completed_at');

        if ($existingCompletedAt !== null) {
            return 'already-complete';
        }

        $hasPartial = DB::table('onboarding_progress')->where('user_id', $userId)->exists()
            || DB::table('user_profiles')->where('user_id', $userId)->exists();

        if ($hasPartial) {
            return 'pre-existing-incomplete-left-untouched';
        }

        try {
            DB::transaction(function () use ($userId): void {
                // The onboarding aggregate is guarded by DEFERRABLE constraint
                // triggers; defer them so the whole consistent aggregate is
                // validated once, at commit.
                DB::statement('SET CONSTRAINTS ALL DEFERRED');

                $now = now();

                DB::table('user_profiles')->insert([
                    'user_id' => $userId,
                    'institution_name' => 'Demo University',
                    'institution_country_code' => 'US',
                    'department' => null,
                    'degree' => null,
                    'major' => null,
                    'year_label' => null,
                    'term_label' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('onboarding_progress')->insert([
                    'user_id' => $userId,
                    'version' => 1,
                    'institution_state' => 'completed',
                    'program_state' => 'skipped',
                    'study_stage_state' => 'skipped',
                    'courses_state' => 'skipped',
                    'goals_state' => 'completed',
                    'first_source_state' => 'skipped',
                    'first_source_url' => null,
                    'first_source_title' => null,
                    'completed_at' => $now,
                    'academic_materialized_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Starter-workspace seed required by the aggregate: one goal.
                DB::table('onboarding_intents')->insert([
                    'user_id' => $userId,
                    'kind' => 'goal',
                    'position' => 0,
                    'text' => 'Stay on top of weekly coursework',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }, 3);

            return 'completed';
        } catch (\Throwable $e) {
            return 'skipped-after-error: '.$e->getMessage();
        }
    }
}
