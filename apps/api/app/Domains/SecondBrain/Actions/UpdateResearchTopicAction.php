<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Models\ResearchTopic;
use App\Domains\SecondBrain\Queries\FindOwnedResearchTopic;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateResearchTopicAction
{
    public function __construct(private FindOwnedResearchTopic $topics) {}

    /** @param array{title: string, description: ?string, keywords: list<string>} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): ResearchTopic
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): ResearchTopic {
                $topic = $this->topics->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $topic);
                $desired = [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'keywords' => $data['keywords'],
                ];

                if ($this->canonical($topic) === $desired) {
                    return $topic->loadCount('sources');
                }

                if ($topic->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $topic->forceFill([
                    ...$desired,
                    'version' => $topic->version + 1,
                ])->save();

                return $topic->refresh()->loadCount('sources');
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'research.update');
        }
    }

    /** @return array{title: string, description: ?string, keywords: list<string>} */
    private function canonical(ResearchTopic $topic): array
    {
        $keywords = $topic->getAttribute('keywords');

        return [
            'title' => $topic->title,
            'description' => $topic->description,
            'keywords' => is_array($keywords) ? array_values($keywords) : [],
        ];
    }
}
