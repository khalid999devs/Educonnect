<?php

declare(strict_types=1);

namespace App\Domains\Courses\Data;

use App\Domains\Courses\Models\Course;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class CourseListResult
{
    /**
     * @param  CursorPaginator<int, Course>  $paginator
     * @param  array{total: int, active: int, archived: int}  $summary
     */
    public function __construct(
        public CursorPaginator $paginator,
        public array $summary,
    ) {}
}
