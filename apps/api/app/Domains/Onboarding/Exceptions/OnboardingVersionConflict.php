<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OnboardingVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The onboarding state changed in another request.');
    }
}
