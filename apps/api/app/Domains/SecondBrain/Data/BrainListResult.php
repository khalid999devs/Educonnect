<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;

/** @template TModel of Model */
final readonly class BrainListResult
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
