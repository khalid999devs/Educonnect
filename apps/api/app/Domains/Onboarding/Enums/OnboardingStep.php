<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Enums;

enum OnboardingStep: string
{
    case Institution = 'institution';
    case Program = 'program';
    case StudyStage = 'study_stage';
    case Courses = 'courses';
    case Goals = 'goals';
    case FirstSource = 'first_source';

    public function stateColumn(): string
    {
        return $this->value.'_state';
    }

    public function isSkippable(): bool
    {
        return $this !== self::Institution;
    }
}
