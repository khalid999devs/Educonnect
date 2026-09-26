<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

use App\Domains\Courses\Models\Course;

/**
 * One bucket in the resources library: either an owned course directory or the
 * single unfiled bucket that collects resources with no course at all.
 */
final readonly class ResourceDirectory
{
    public const KIND_COURSE = 'course';

    public const KIND_UNFILED = 'unfiled';

    private function __construct(
        public string $kind,
        public ?Course $course,
        public int $resourceCount,
    ) {}

    public static function forCourse(Course $course, int $resourceCount): self
    {
        return new self(self::KIND_COURSE, $course, $resourceCount);
    }

    public static function unfiled(int $resourceCount): self
    {
        return new self(self::KIND_UNFILED, null, $resourceCount);
    }
}
