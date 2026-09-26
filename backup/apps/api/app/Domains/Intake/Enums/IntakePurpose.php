<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

/**
 * Why a student captured something. This is the organizing principle of the
 * Second Brain and Study surfaces: it decides which actions a captured item
 * offers, not who may read it.
 *
 * The cases align exactly with the `knowledge_items_purpose_known` CHECK added
 * by migration 2026_07_20_000019. Adding a case here without widening that
 * CHECK produces an SQLSTATE 23514 on write, so the two must move together.
 *
 * Do not shorten this to a bare `Purpose`: `tools.purpose` is an unrelated
 * curated text column describing a catalog tool.
 */
enum IntakePurpose: string
{
    case Resource = 'resource';
    case Study = 'study';
    case Research = 'research';
    case Exam = 'exam';

    /**
     * The purpose assumed when no signal favours another: keeping the material
     * findable. It is the safest wrong answer, because every other purpose is
     * still one click away.
     */
    public static function default(): self
    {
        return self::Resource;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
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
