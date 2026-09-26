<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

final class OnboardingOpenApiContractTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesOpenApiSpec;

    /** @var list<string> */
    protected array $responseCodesToSkip = ['^$'];

    public function test_verified_student_can_resume_every_step_and_complete_against_the_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 0)
            ->assertJsonPath('data.onboarding.status', 'not_started')
            ->assertJsonPath('data.onboarding.current_step', 'institution')
            ->assertJsonPath('data.onboarding.steps.institution', 'pending')
            ->assertJsonPath('data.onboarding.starter_context', null)
            ->assertJsonMissingPath('data.onboarding.id')
            ->assertJsonMissingPath('data.onboarding.user_id');

        $version = $this->updateStep('institution', 0, [
            'state' => 'completed',
            'data' => [
                'institution_name' => '  Example University  ',
                'institution_country_code' => 'bd',
            ],
        ])->assertJsonPath('data.onboarding.profile.institution_name', 'Example University')
            ->assertJsonPath('data.onboarding.profile.institution_country_code', 'BD')
            ->json('data.onboarding.version');

        $this->assertSame(1, $version);

        $version = $this->updateStep('program', $version, [
            'state' => 'completed',
            'data' => [
                'department' => 'Computer Science',
                'degree' => 'BSc',
                'major' => null,
            ],
        ])->json('data.onboarding.version');

        $version = $this->updateStep('study_stage', $version, [
            'state' => 'completed',
            'data' => [
                'year_label' => 'Year 3',
                'term_label' => 'Term 2',
            ],
        ])->json('data.onboarding.version');

        $version = $this->updateStep('courses', $version, [
            'state' => 'completed',
            'data' => [
                'courses' => [
                    ['title' => 'Software Engineering', 'code' => 'CSE 3200'],
                ],
            ],
        ])->assertJsonPath('data.onboarding.course_drafts.0.title', 'Software Engineering')
            ->json('data.onboarding.version');

        $version = $this->updateStep('goals', $version, [
            'state' => 'completed',
            'data' => [
                'goals' => ['Plan weekly coursework'],
                'problems' => ['Materials are scattered'],
            ],
        ])->json('data.onboarding.version');

        $version = $this->updateStep('first_source', $version, [
            'state' => 'completed',
            'data' => [
                'url' => 'https://example.edu/syllabus',
                'title' => 'Course syllabus',
            ],
        ])->assertJsonPath('data.onboarding.can_complete', true)
            ->json('data.onboarding.version');

        $this->assertSame(6, $version);

        $completion = $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/completion', ['expected_version' => $version])
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 7)
            ->assertJsonPath('data.onboarding.status', 'completed')
            ->assertJsonPath('data.onboarding.current_step', null)
            ->assertJsonPath('data.onboarding.starter_context.institution.name', 'Example University')
            ->assertJsonPath('data.onboarding.starter_context.course_drafts.0.code', 'CSE 3200')
            ->assertJsonMissingPath('data.onboarding.starter_context.recommendations');

        $completedAt = $completion->json('data.onboarding.completed_at');
        $this->assertIsString($completedAt);

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/completion', ['expected_version' => $version])
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 7)
            ->assertJsonPath('data.onboarding.completed_at', $completedAt);
    }

    public function test_step_retry_conflict_and_strict_validation_errors_match_the_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $payload = [
            'expected_version' => 0,
            'state' => 'completed',
            'data' => [
                'institution_name' => 'Example University',
                'institution_country_code' => 'BD',
            ],
        ];

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/institution', $payload)
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 1);

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/institution', $payload)
            ->assertOk()
            ->assertJsonPath('data.onboarding.version', 1);

        $payload['data']['institution_name'] = 'Changed University';

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/institution', $payload)
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/completion', ['expected_version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/institution', [
                'expected_version' => 1,
                'state' => 'completed',
                'data' => [
                    'institution_name' => 'Example University',
                    'institution_country_code' => 'ZZ',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['data.institution_country_code']]],
            ]);

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/institution', [
                'expected_version' => 1,
                'state' => 'skipped',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->updateStep('program', 1, ['state' => 'skipped'])
            ->assertJsonPath('data.onboarding.version', 2)
            ->assertJsonPath('data.onboarding.steps.program', 'skipped');

        $this->updateStep('program', 1, ['state' => 'skipped'])
            ->assertJsonPath('data.onboarding.version', 2);

        $this->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/first_source', [
                'expected_version' => 2,
                'state' => 'completed',
                'data' => [
                    'url' => 'https://student:secret@example.edu/source',
                    'title' => null,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/courses', [
                'expected_version' => 2,
                'state' => 'completed',
                'data' => [
                    'courses' => [
                        'named' => ['title' => 'Not a JSON list', 'code' => null],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['data.courses']]],
            ]);

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/goals', [
                'expected_version' => 2,
                'state' => 'completed',
                'data' => [
                    'goals' => ['named' => 'Not a JSON list'],
                    'problems' => [],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['data.goals']]],
            ]);

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulMutationHeaders())
            ->putJson('/api/v1/onboarding/steps/program', [
                'expected_version' => 2,
                'state' => 'completed',
                'data' => ['department' => '<script>private</script>'],
                'user_id' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['user_id', 'data.department']]]]);
    }

    public function test_authentication_and_verification_denials_match_the_contract(): void
    {
        $this->withoutRequestValidation()
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/onboarding')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');

        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'web');

        $this->withoutRequestValidation()
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/onboarding')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTHORIZATION_DENIED');
    }

    /**
     * @param  array<string, mixed>  $stepPayload
     */
    private function updateStep(string $step, int $expectedVersion, array $stepPayload): TestResponse
    {
        return $this->withHeaders($this->statefulMutationHeaders())
            ->putJson("/api/v1/onboarding/steps/{$step}", [
                'expected_version' => $expectedVersion,
                ...$stepPayload,
            ])
            ->assertOk();
    }

    /** @return array<string, string> */
    private function statefulMutationHeaders(): array
    {
        return [
            ...$this->statefulHeaders(),
            'X-XSRF-TOKEN' => 'contract-test-token',
        ];
    }

    /** @return array<string, string> */
    private function statefulHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ];
    }

    protected function getAuthenticatedRequest(SymfonyRequest $request): SymfonyRequest
    {
        $authenticatedRequest = clone $request;
        $authenticatedRequest->cookies->set('__Host-educonnect-session', 'contract-session');

        return $authenticatedRequest;
    }
}
