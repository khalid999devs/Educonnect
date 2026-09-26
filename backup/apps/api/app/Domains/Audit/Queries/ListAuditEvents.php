<?php

declare(strict_types=1);

namespace App\Domains\Audit\Queries;

use App\Domains\Audit\Models\AuditEvent;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListAuditEvents
{
    /**
     * The append-only audit trail, newest first. Filters narrow by action,
     * actor, or subject type; every filter is an exact match on an indexed
     * column. Callers must already hold the audit-view capability (enforced at
     * the route).
     *
     * @return CursorPaginator<int, AuditEvent>
     */
    public function execute(
        ?string $action,
        ?string $actorPublicId,
        ?string $subjectType,
        int $perPage,
    ): CursorPaginator {
        $query = AuditEvent::query()
            ->with('actor')
            ->select(['audit_events.*', 'audit_events.created_at as cursor_created_at_desc']);

        if ($action !== null) {
            $query->where('action', $action);
        }

        if ($actorPublicId !== null) {
            $query->where('actor_public_id', $actorPublicId);
        }

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }

        return $query
            ->orderBy('cursor_created_at_desc', 'desc')
            ->orderBy('public_id', 'desc')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }
}
