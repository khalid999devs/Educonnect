<?php

declare(strict_types=1);

namespace App\Domains\Study\Enums;

/**
 * The generation lifecycle of one study artifact. Values mirror the
 * `study_artifacts_status_known` CHECK in `..._000019` exactly.
 *
 * Two database CHECKs make the honest-failure rule structural rather than a
 * convention: `ready` is the only status that may carry a payload, and
 * `failed` is the only status that may carry a failure_reason. There is no
 * state in which fabricated study material can exist.
 */
enum StudyArtifactStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';

    /** True while a worker still owes the student an answer. */
    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function isTerminal(): bool
    {
        return ! $this->isPending();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
