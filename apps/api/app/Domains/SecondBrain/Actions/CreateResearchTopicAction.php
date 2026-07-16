<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateResearchTopicAction
{
    /** @param array{title: string, description: ?string, keywords: list<string>} $data */
    public function execute(User $user, array $data): ResearchTopic
    {
        Gate::forUser($user)->authorize('create', ResearchTopic::class);

        try {
            return DB::transaction(function () use ($user, $data): ResearchTopic {
                $topic = new ResearchTopic;
                $topic->forceFill([
                    'user_id' => $user->getKey(),
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'keywords' => $data['keywords'],
                    'version' => 1,
                ])->save();

                return $topic->loadCount('sources');
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'research.create');
        }
    }
}
