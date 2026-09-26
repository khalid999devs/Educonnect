<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Onboarding\Data\OnboardingSnapshot;
use App\Domains\Onboarding\Enums\OnboardingStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin OnboardingSnapshot */
final class OnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->snapshot();
        $profile = $snapshot->profile;
        $courseDrafts = array_map(
            static fn (array $course): array => [
                'title' => $course['title'],
                'code' => $course['code'],
            ],
            $snapshot->courses,
        );
        $firstSourceDraft = $snapshot->firstSource === null
            ? null
            : [
                'url' => $snapshot->firstSource['url'],
                'title' => $snapshot->firstSource['title'],
            ];

        return [
            'version' => $snapshot->version,
            'status' => $snapshot->status->value,
            'current_step' => $snapshot->currentStep?->value,
            'can_complete' => $snapshot->canComplete,
            'completed_at' => $snapshot->completedAt?->toISOString(),
            'steps' => $this->stepStates($snapshot),
            'profile' => [
                'institution_name' => $profile['institution_name'],
                'institution_country_code' => $profile['institution_country_code'],
                'department' => $profile['department'],
                'degree' => $profile['degree'],
                'major' => $profile['major'],
                'year_label' => $profile['year_label'],
                'term_label' => $profile['term_label'],
            ],
            'course_drafts' => $courseDrafts,
            'goals' => $snapshot->goals,
            'problems' => $snapshot->problems,
            'first_source_draft' => $firstSourceDraft,
            'starter_context' => $snapshot->status->value === 'completed'
                ? [
                    'institution' => [
                        'name' => $profile['institution_name'],
                        'country_code' => $profile['institution_country_code'],
                    ],
                    'program' => [
                        'department' => $profile['department'],
                        'degree' => $profile['degree'],
                        'major' => $profile['major'],
                    ],
                    'study_stage' => [
                        'year_label' => $profile['year_label'],
                        'term_label' => $profile['term_label'],
                    ],
                    'course_drafts' => $courseDrafts,
                    'goals' => $snapshot->goals,
                    'problems' => $snapshot->problems,
                    'first_source_draft' => $firstSourceDraft,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stepStates(OnboardingSnapshot $snapshot): array
    {
        $states = [];

        foreach (OnboardingStep::cases() as $step) {
            $states[$step->value] = $snapshot->steps[$step->value]->value;
        }

        return $states;
    }

    private function snapshot(): OnboardingSnapshot
    {
        if (! $this->resource instanceof OnboardingSnapshot) {
            throw new LogicException('The onboarding resource requires an onboarding snapshot.');
        }

        return $this->resource;
    }
}
