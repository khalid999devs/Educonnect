<?php

declare(strict_types=1);

namespace App\Domains\Users\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Data\SettingsSnapshot;
use App\Domains\Users\Exceptions\UserPersistenceFailure;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\BuildOwnSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Updates the account fields a signed-in user may change without re-authenticating.
 *
 * Only the display name qualifies today. Email changes, password changes, and
 * account deletion are deliberately out of scope: each needs a verification or
 * re-authentication record that does not exist in the schema yet.
 */
final readonly class UpdateOwnAccountAction
{
    public function __construct(private BuildOwnSettings $settings) {}

    /**
     * @param  array{name: string}  $data
     */
    public function execute(User $user, array $data): SettingsSnapshot
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            DB::transaction(function () use ($user, $data): void {
                if ($user->name === $data['name']) {
                    return;
                }

                $user->forceFill(['name' => $data['name']])->save();
            }, 3);
        } catch (QueryException $exception) {
            throw UserPersistenceFailure::fromQueryException($exception, 'settings.account.update');
        }

        return $this->settings->execute($user->refresh());
    }
}
