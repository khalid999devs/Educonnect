<?php

declare(strict_types=1);

namespace App\Domains\Tools\Actions;

use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Domains\Tools\Exceptions\ToolPersistenceFailure;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Tools\Queries\FindPublishedTool;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetToolPreferenceAction
{
    public function __construct(private FindPublishedTool $tools) {}

    public function execute(User $user, string $publicId, ToolPreferenceState $state): Tool
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $state): Tool {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $tool = $this->tools->execute($user, $publicId, lockForUpdate: true);

                $preference = UserToolPreference::query()
                    ->where('user_id', $user->getKey())
                    ->where('tool_id', $tool->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($preference instanceof UserToolPreference) {
                    Gate::forUser($user)->authorize('update', $preference);
                    $current = $preference->getAttribute('state');
                    $currentValue = $current instanceof ToolPreferenceState ? $current : ToolPreferenceState::tryFrom((string) $current);

                    if ($currentValue === $state) {
                        $tool->setAttribute('viewer_preference_state', $state->value);

                        return $tool;
                    }

                    $preference->forceFill(['state' => $state])->save();
                    $tool->setAttribute('viewer_preference_state', $state->value);

                    return $tool;
                }

                Gate::forUser($user)->authorize('create', UserToolPreference::class);

                $preference = new UserToolPreference;
                $preference->forceFill([
                    'user_id' => $user->getKey(),
                    'tool_id' => $tool->getKey(),
                    'state' => $state,
                ])->save();
                $tool->setAttribute('viewer_preference_state', $state->value);

                return $tool;
            }, 3);
        } catch (QueryException $exception) {
            throw ToolPersistenceFailure::fromQueryException($exception, 'tool.preference.set');
        }
    }
}
