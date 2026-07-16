<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Actions;

use App\Domains\SecondBrain\Exceptions\BrainPersistenceFailure;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateCollectionAction
{
    /** @param array{name: string, description: ?string, kind: string} $data */
    public function execute(User $user, array $data): Collection
    {
        Gate::forUser($user)->authorize('create', Collection::class);

        try {
            return DB::transaction(function () use ($user, $data): Collection {
                $this->assertNameAvailable($user, $data['name']);

                $collection = new Collection;
                $collection->forceFill([
                    'user_id' => $user->getKey(),
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'kind' => $data['kind'],
                    'version' => 1,
                ])->save();

                return $collection->loadCount('knowledgeItems');
            }, 3);
        } catch (QueryException $exception) {
            throw BrainPersistenceFailure::fromQueryException($exception, 'collection.create');
        }
    }

    private function assertNameAvailable(User $user, string $name, ?int $ignoreId = null): void
    {
        $query = Collection::query()
            ->where('user_id', $user->getKey())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['A collection with this name already exists.'],
            ]);
        }
    }
}
