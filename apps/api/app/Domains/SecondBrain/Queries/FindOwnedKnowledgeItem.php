<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedKnowledgeItem
{
    public function execute(
        User $user,
        string $publicId,
        bool $lockForUpdate = false,
        bool $withDetail = false,
    ): KnowledgeItem {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = KnowledgeItem::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId);

            if ($withDetail) {
                $query->with([
                    'resource',
                    'intakeItem',
                    'notes' => static fn ($notes) => $notes->orderByDesc('created_at')->orderByDesc('id'),
                    'tags' => static fn ($tags) => $tags->orderBy('name'),
                    'collections' => static fn ($collections) => $collections->orderBy('name'),
                    'outgoingLinks.toItem',
                    'incomingLinks.fromItem',
                ]);
            }

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $item = $query->first();

            if (! $item instanceof KnowledgeItem) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $item);

            return $item;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw BrainPersistenceFailure::fromQueryException($exception, 'knowledge.read');
        }
    }
}
