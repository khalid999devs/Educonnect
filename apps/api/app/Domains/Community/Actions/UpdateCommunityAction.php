<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Exceptions\CommunityVersionConflict;
use App\Domains\Community\Models\Community;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommunityAction
{
    /**
     * Edit a community's presentation (name, summary, description, topic). The
     * slug is immutable so existing links keep working; visibility is changed by
     * its own action. Optimistic concurrency via expected_version.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Community $community, array $data, int $expectedVersion): Community
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Community management is not allowed.');
        }

        return DB::transaction(function () use ($community, $data, $expectedVersion): Community {
            $locked = Community::query()->lockForUpdate()->findOrFail($community->getKey());

            if ($locked->version !== $expectedVersion) {
                throw new CommunityVersionConflict;
            }

            $locked->forceFill([
                'name' => $data['name'],
                'summary' => $data['summary'],
                'description' => $data['description'] ?? null,
                'topic' => $data['topic'] ?? null,
                'version' => $locked->version + 1,
            ])->save();

            return $locked;
        }, 3);
    }
}
