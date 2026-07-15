<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Guidance\Enums\GuidancePreferenceState;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Templates\Queries\FindPublishedTemplate;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ClearTemplatePreferenceAction
{
    public function __construct(private FindPublishedTemplate $templates) {}

    public function execute(User $user, string $publicId, GuidancePreferenceState $state): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $state): void {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $template = $this->templates->execute($user, $publicId, lockForUpdate: true);

                $preference = UserTemplatePreference::query()
                    ->where('user_id', $user->getKey())
                    ->where('template_id', $template->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $preference instanceof UserTemplatePreference) {
                    return;
                }

                Gate::forUser($user)->authorize('delete', $preference);
                $current = $preference->getAttribute('state');
                $currentValue = $current instanceof GuidancePreferenceState ? $current : GuidancePreferenceState::tryFrom((string) $current);

                if ($currentValue === $state) {
                    $preference->delete();
                }
            }, 3);
        } catch (QueryException $exception) {
            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.preference.clear');
        }
    }
}
