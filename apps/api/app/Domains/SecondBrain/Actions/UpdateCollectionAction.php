<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Exceptions\BrainVersionConflict;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Queries\FindOwnedCollection;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class UpdateCollectionAction
{
    public function __construct(private FindOwnedCollection $collections) {}

    /** @param array{name: string, description: ?string, kind: string} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): Collection
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): Collection {
                $collection = $this->collections->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $collection);
                $desired = [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'kind' => $data['kind'],
                ];

                if ($this->canonical($collection) === $desired) {
                    return $collection->loadCount('knowledgeItems');
                }

                if ($collection->version !== $expectedVersion) {
                    throw new BrainVersionConflict;
                }

                $this->assertNameAvailable($user, $data['name'], (int) $collection->getKey());

                $collection->forceFill([
                    ...$desired,
                    'version' => $collection->version + 1,
                ])->save();

                return $collection->refresh()->loadCount('knowledgeItems');
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'collection.update');
        }
    }

    /** @return array{name: string, description: ?string, kind: string} */
    private function canonical(Collection $collection): array
    {
        return [
            'name' => $collection->name,
            'description' => $collection->description,
            'kind' => $collection->kind,
        ];
    }

    private function assertNameAvailable(User $user, string $name, int $ignoreId): void
    {
        $exists = Collection::query()
            ->where('user_id', $user->getKey())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->whereKeyNot($ignoreId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A collection with this name already exists.'],
            ]);
        }
    }
}
