<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Mentor\Enums\MentorVerificationState;
use App\Domains\Mentor\Exceptions\MentorPersistenceFailure;
use App\Domains\Mentor\Exceptions\MentorStateConflict;
use App\Domains\Mentor\Exceptions\MentorVersionConflict;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class SetMentorVerificationAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function execute(
        User $actor,
        string $profilePublicId,
        MentorVerificationState $target,
        string $reason,
        string $requestId,
        int $expectedVersion,
    ): MentorProfile {
        if (! $actor->hasCapability(CapabilityKey::MentorsCurate)) {
            throw new AuthorizationException('Mentor curation is not allowed.');
        }

        try {
            return DB::transaction(function () use ($actor, $profilePublicId, $target, $reason, $requestId, $expectedVersion): MentorProfile {
                $profile = MentorProfile::query()
                    ->where('public_id', $profilePublicId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($profile->version !== $expectedVersion) {
                    throw new MentorVersionConflict;
                }

                $current = $profile->verification_state;

                if ($current === $target) {
                    throw new MentorStateConflict("This mentor is already {$target->value}.");
                }

                $profile->forceFill([
                    'verification_state' => $target->value,
                    'version' => $profile->version + 1,
                ])->save();

                $this->auditRecorder->record(
                    actor: $actor,
                    action: AuditAction::MentorVerificationChanged,
                    subjectType: 'mentor_profile',
                    subjectId: (string) $profile->public_id,
                    reason: $reason,
                    requestId: $requestId,
                    beforeState: ['verification_state' => [$current->value]],
                    afterState: ['verification_state' => [$target->value]],
                );

                return $profile->load('user');
            }, 3);
        } catch (QueryException $exception) {
            throw MentorPersistenceFailure::fromQueryException($exception, 'mentor.verification');
        }
    }
}
