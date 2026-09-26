<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Enums;

use App\Domains\Intake\Enums\IntakePurpose;

/**
 * Why a knowledge item exists, from the owner's point of view. Purpose decides
 * which actions the Second Brain and Study surfaces offer for an item. It is
 * never an authorization signal: ownership already answers who may read a row.
 *
 * The cases align exactly with the `knowledge_items_purpose_known` CHECK added
 * by migration 2026_07_20_000019. Adding a case here without widening that
 * CHECK produces an SQLSTATE 23514 on write, so the two must move together.
 *
 * `null` is a legitimate value on the column: every row written before the
 * migration reads back as "no purpose recorded", which is different from any
 * particular purpose and must not be defaulted on read.
 *
 * Do not shorten this to a bare `Purpose`: `tools.purpose` is an unrelated
 * curated text column describing a catalog tool.
 */
enum KnowledgePurpose: string
{
    case Resource = 'resource';
    case Study = 'study';
    case Research = 'research';
    case Exam = 'exam';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Bridges the advisory purpose the intake router produces into the value
     * persisted on a knowledge item. The two enums are deliberately separate:
     * intake owns a suggestion, the Second Brain owns the stored fact.
     */
    public static function fromIntakePurpose(IntakePurpose $purpose): self
    {
        return match ($purpose) {
            IntakePurpose::Resource => self::Resource,
            IntakePurpose::Study => self::Study,
            IntakePurpose::Research => self::Research,
            IntakePurpose::Exam => self::Exam,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Resource => 'Keep as a resource',
            self::Study => 'Study this',
            self::Research => 'Research material',
            self::Exam => 'Exam preparation',
        };
    }
}
