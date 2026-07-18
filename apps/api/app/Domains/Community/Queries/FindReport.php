<?php

declare(strict_types=1);

namespace App\Domains\Community\Queries;

use App\Domains\Community\Models\ContentReport;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindReport
{
    public function execute(string $publicId, bool $lockForUpdate = false): ContentReport
    {
        $query = ContentReport::query()
            ->with(['community', 'post', 'comment', 'reporter'])
            ->where('public_id', $publicId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $report = $query->first();

        if (! $report instanceof ContentReport) {
            throw new NotFoundHttpException;
        }

        return $report;
    }
}
