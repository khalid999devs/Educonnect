<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Queries\FindOwnedResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteResearchTopicAction
{
    public function __construct(private FindOwnedResearchTopic $topics) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $topic = $this->topics->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $topic);

                if ($topic->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $topic->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'research.delete');
        }
    }
}
