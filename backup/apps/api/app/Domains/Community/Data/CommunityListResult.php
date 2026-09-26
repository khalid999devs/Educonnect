<?php

declare(strict_types=1);

namespace App\Domains\Community\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;

/** @template TModel of Model */
final readonly class CommunityListResult
{
    /**
     * @param  CursorPaginator<int, TModel>  $paginator
     * @param  array<string, int>  $summary
     */
    public function __construct(
        public CursorPaginator $paginator,
        public array $summary,
    ) {}
}
