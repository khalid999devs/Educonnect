<?php

declare(strict_types=1);

namespace App\Domains\Study\Data;

use App\Domains\Study\Models\StudyArtifact;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class StudyListResult
{
    /**
     * @param  CursorPaginator<int, StudyArtifact>  $paginator
     * @param  array<string, int>  $summary
     */
    public function __construct(
        public CursorPaginator $paginator,
        public array $summary,
    ) {}
}
