<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Guidance\Queries\FindPublishedPrompt;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetPromptPreferenceAction
{
    public function __construct(private FindPublishedPrompt $prompts) {}

    public function execute(User $user, string $publicId, GuidancePreferenceState $state): PromptTemplate
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $state): PromptTemplate {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $prompt = $this->prompts->execute($user, $publicId, lockForUpdate: true);

                $preference = UserPromptPreference::query()
                    ->where('user_id', $user->getKey())
                    ->where('prompt_template_id', $prompt->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($preference instanceof UserPromptPreference) {
                    Gate::forUser($user)->authorize('update', $preference);
                    $current = $preference->getAttribute('state');
                    $currentValue = $current instanceof GuidancePreferenceState ? $current : GuidancePreferenceState::tryFrom((string) $current);

                    if ($currentValue === $state) {
                        $prompt->setAttribute('viewer_preference_state', $state->value);

                        return $prompt;
                    }

                    $preference->forceFill(['state' => $state])->save();
                    $prompt->setAttribute('viewer_preference_state', $state->value);

                    return $prompt;
                }

                Gate::forUser($user)->authorize('create', UserPromptPreference::class);

                $preference = new UserPromptPreference;
                $preference->forceFill([
                    'user_id' => $user->getKey(),
                    'prompt_template_id' => $prompt->getKey(),
                    'state' => $state,
                ])->save();
                $prompt->setAttribute('viewer_preference_state', $state->value);

                return $prompt;
            }, 3);
        } catch (QueryException $exception) {
            throw GuidancePersistenceFailure::fromQueryException($exception, 'prompt.preference.set');
        }
    }
}
