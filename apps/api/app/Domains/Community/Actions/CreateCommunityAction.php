<?php

declare(strict_types=1);

namespace App\Domains\Community\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Community\Enums\CommunityVisibility;
use App\Domains\Community\Models\Community;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateCommunityAction
{
    /**
     * Create an admin-managed community, published and visible immediately. The
     * slug is derived from the name and made unique.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Community
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Community management is not allowed.');
        }

        return DB::transaction(function () use ($data): Community {
            $community = new Community;
            $community->forceFill([
                'slug' => $this->uniqueSlug((string) $data['name']),
                'name' => $data['name'],
                'summary' => $data['summary'],
                'description' => $data['description'] ?? null,
                'topic' => $data['topic'] ?? null,
                'visibility' => CommunityVisibility::Published->value,
                'is_seeded' => false,
                'version' => 1,
            ])->save();

            return $community;
        }, 3);
    }

    private function uniqueSlug(string $name): string
    {
        $base = mb_substr(Str::slug($name) ?: 'community', 0, 76);
        $slug = $base;
        $suffix = 2;

        while (Community::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
