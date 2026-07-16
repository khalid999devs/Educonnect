<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

enum IntakeState: string
{
    case UploadedOrLinked = 'uploaded_or_linked';
    case Queued = 'queued';
    case Extracting = 'extracting';
    case Extracted = 'extracted';
    case Organizing = 'organizing';
    case AwaitingReview = 'awaiting_review';
    case Confirmed = 'confirmed';
    case Saved = 'saved';
    case FailedRetryable = 'failed_retryable';
    case FailedFinal = 'failed_final';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Saved, self::FailedFinal, self::Cancelled], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::UploadedOrLinked,
            self::Queued,
            self::Extracting,
            self::Extracted,
            self::Organizing,
            self::AwaitingReview,
            self::FailedRetryable,
        ], true);
    }
}
