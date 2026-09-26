<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedTemplateCopy
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): UserTemplateCopy
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = UserTemplateCopy::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->with(['template', 'templateVersion', 'course']);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $copy = $query->first();

            if (! $copy instanceof UserTemplateCopy) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $copy);

            return $copy;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.copy.read');
        }
    }
}
