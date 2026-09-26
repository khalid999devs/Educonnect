<?php

declare(strict_types=1);

namespace App\Domains\Content\Enums;

/**
 * The curation lifecycle transitions shared by every platform-owned catalog
 * type (tools, prompts, workflows). The database transition trigger is the
 * authority on which transitions are legal from a given state; this enum names
 * the caller's intent and maps it to the target state.
 */
enum ContentTransition: string
{
    case SubmitForReview = 'submit_for_review';
    case Publish = 'publish';
    case Archive = 'archive';
    case ReturnToDraft = 'return_to_draft';

    public function targetState(): string
    {
        return match ($this) {
            self::SubmitForReview => 'in_review',
            self::Publish => 'published',
            self::Archive => 'archived',
            self::ReturnToDraft => 'draft',
        };
    }
}
