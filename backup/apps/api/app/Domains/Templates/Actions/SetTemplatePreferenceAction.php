<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Templates\Queries\FindPublishedTemplate;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetTemplatePreferenceAction
{
    public function __construct(private FindPublishedTemplate $templates) {}

    public function execute(User $user, string $publicId, GuidancePreferenceState $state): Template
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $state): Template {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $template = $this->templates->execute($user, $publicId, lockForUpdate: true);

                $preference = UserTemplatePreference::query()
                    ->where('user_id', $user->getKey())
                    ->where('template_id', $template->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($preference instanceof UserTemplatePreference) {
                    Gate::forUser($user)->authorize('update', $preference);
                    $current = $preference->getAttribute('state');
                    $currentValue = $current instanceof GuidancePreferenceState ? $current : GuidancePreferenceState::tryFrom((string) $current);

                    if ($currentValue === $state) {
                        $template->setAttribute('viewer_preference_state', $state->value);

                        return $template;
                    }

                    $preference->forceFill(['state' => $state])->save();
                    $template->setAttribute('viewer_preference_state', $state->value);

                    return $template;
                }

                Gate::forUser($user)->authorize('create', UserTemplatePreference::class);

                $preference = new UserTemplatePreference;
                $preference->forceFill([
                    'user_id' => $user->getKey(),
                    'template_id' => $template->getKey(),
                    'state' => $state,
                ])->save();
                $template->setAttribute('viewer_preference_state', $state->value);

                return $template;
            }, 3);
        } catch (QueryException $exception) {
            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.preference.set');
        }
    }
}
