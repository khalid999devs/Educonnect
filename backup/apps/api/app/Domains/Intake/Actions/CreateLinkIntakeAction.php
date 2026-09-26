<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Enums\IntakeSourceType;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Exceptions\UnsafeIntakeUrl;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Intake\Support\SafeIntakeUrl;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateLinkIntakeAction
{
    public function __construct(
        private SafeIntakeUrl $safeUrl,
        private IntakeEventRecorder $events,
    ) {}

    public function execute(User $user, string $url, ?string $context): IntakeItem
    {
        Gate::forUser($user)->authorize('create', IntakeItem::class);

        try {
            $safe = $this->safeUrl->assertSafe($url);
        } catch (UnsafeIntakeUrl) {
            throw ValidationException::withMessages([
                'url' => ['This link cannot be ingested safely.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $safe, $context): IntakeItem {
                $item = new IntakeItem;
                $item->forceFill([
                    'user_id' => $user->getKey(),
                    'source_type' => IntakeSourceType::Link->value,
                    'url' => $safe['url'],
                    'context' => $context,
                    'state' => IntakeState::UploadedOrLinked->value,
                ])->save();

                $this->events->record($item, 'created', null, IntakeState::UploadedOrLinked, 'link intake created');

                $item->forceFill([
                    'state' => IntakeState::Queued->value,
                    'queued_at' => now(),
                ])->save();
                $this->events->record($item, 'queued', IntakeState::UploadedOrLinked, IntakeState::Queued);

                ProcessIntakeItem::dispatch((int) $item->getKey())->afterCommit();

                return $item->refresh()->load(['resource', 'artifacts', 'events']);
            }, 3);
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.create');
        }
    }
}
