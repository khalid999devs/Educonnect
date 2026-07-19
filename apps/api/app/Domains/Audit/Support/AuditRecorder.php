<?php

declare(strict_types=1);

namespace App\Domains\Audit\Support;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Users\Models\User;
use App\Support\RequestId;
use InvalidArgumentException;

final class AuditRecorder
{
    /**
     * @param  array<string, list<string>>  $beforeState
     * @param  array<string, list<string>>  $afterState
     */
    public function record(
        ?User $actor,
        AuditAction $action,
        string $subjectType,
        string $subjectId,
        string $reason,
        string $requestId,
        array $beforeState,
        array $afterState,
    ): AuditEvent {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('An audit reason between 1 and 2000 characters is required.');
        }

        if (preg_match(RequestId::PATTERN, $requestId) !== 1) {
            throw new InvalidArgumentException('A valid server request ID is required for audit events.');
        }

        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $subjectType) !== 1) {
            throw new InvalidArgumentException('The audit subject type is invalid.');
        }

        if (trim($subjectId) === '' || mb_strlen($subjectId) > 128) {
            throw new InvalidArgumentException('The audit subject identifier is invalid.');
        }

        $this->assertSafeState($beforeState);
        $this->assertSafeState($afterState);

        $event = new AuditEvent;
        $event->forceFill([
            'actor_type' => $actor === null ? 'system' : 'user',
            'actor_user_id' => $actor?->getKey(),
            'actor_public_id' => $actor?->public_id,
            'action' => $action->value,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'request_id' => $requestId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ])->saveOrFail();

        return $event;
    }

    /**
     * @param  array<string, list<string>>  $state
     */
    private function assertSafeState(array $state): void
    {
        // The allowlist keeps audit payloads to change-descriptive metadata only - 
        // never secrets or private academic/content bodies (doc 08). Each value is a
        // short list of stable enum-like strings describing what changed.
        $allowedKeys = [
            'role_keys',
            'capability_keys',
            'account_status',
            'verification_state',
            'report_status',
            'moderation_state',
            'content_state',
            'visibility',
            'demo_data',
        ];

        if (array_diff(array_keys($state), $allowedKeys) !== []) {
            throw new InvalidArgumentException('Audit state contains a non-allowlisted field.');
        }

        $encoded = json_encode($state, JSON_THROW_ON_ERROR);

        if (strlen($encoded) > 16_384) {
            throw new InvalidArgumentException('Audit state exceeds the storage limit.');
        }
    }
}
