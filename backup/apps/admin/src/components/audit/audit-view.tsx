"use client";

import { Badge, Button, ErrorState, Select, Skeleton } from "@educonnect/ui";
import { useInfiniteQuery } from "@tanstack/react-query";
import { useState } from "react";

import { listAuditEvents } from "@/lib/api/admin-audit";
import { formatDateTime, humanizeKey } from "@/lib/format";
import { auditKeys } from "@/lib/query-keys";

const ACTIONS = [
  "authorization.user-roles-changed",
  "authorization.role-capabilities-changed",
  "users.account-suspended",
  "users.account-reactivated",
  "mentors.verification-changed",
  "community.report-resolved",
];

function describeState(state: Record<string, unknown>): string {
  const entries = Object.entries(state);

  if (entries.length === 0) {
    return "-";
  }

  return entries
    .map(([key, value]) => {
      const rendered = Array.isArray(value) ? value.join(", ") : String(value);

      return `${humanizeKey(key)}: ${rendered}`;
    })
    .join("; ");
}

export function AuditView() {
  const [action, setAction] = useState("");

  const query = useInfiniteQuery({
    queryKey: auditKeys.list(action),
    queryFn: ({ pageParam }) =>
      listAuditEvents({ action: action || undefined, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const events = query.data?.pages.flatMap((page) => page.items) ?? [];

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Audit log</h1>
        <p className="text-body text-text-secondary">
          An append-only record of every sensitive administrative action: actor,
          action, subject, reason, and what changed.
        </p>
      </header>

      <div className="w-72">
        <Select
          value={action}
          onChange={(event) => setAction(event.target.value)}
          aria-label="Filter by action"
        >
          <option value="">All actions</option>
          {ACTIONS.map((value) => (
            <option key={value} value={value}>
              {humanizeKey(value)}
            </option>
          ))}
        </Select>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load the audit log"
          description="The audit trail could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : events.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No audit events match this filter.
        </p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border-subtle">
          <table className="w-full border-collapse text-left text-body">
            <thead>
              <tr className="border-b border-border-subtle bg-bg-surface text-caption uppercase tracking-wide text-text-muted">
                <th className="px-4 py-3 font-medium">When</th>
                <th className="px-4 py-3 font-medium">Action</th>
                <th className="px-4 py-3 font-medium">Actor</th>
                <th className="px-4 py-3 font-medium">Change</th>
              </tr>
            </thead>
            <tbody>
              {events.map((event) => (
                <tr
                  key={event.id}
                  className="border-b border-border-subtle align-top last:border-0"
                >
                  <td className="whitespace-nowrap px-4 py-3 text-text-secondary">
                    {formatDateTime(event.created_at)}
                  </td>
                  <td className="px-4 py-3">
                    <Badge>{humanizeKey(event.action)}</Badge>
                    <span className="mt-1 block text-caption text-text-muted">
                      {event.reason}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-text-secondary">
                    {event.actor.name ?? event.actor.type}
                  </td>
                  <td className="px-4 py-3 text-caption text-text-secondary">
                    <span className="block">
                      {describeState(event.before_state)}
                    </span>
                    <span className="block text-text-primary">
                      → {describeState(event.after_state)}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {query.hasNextPage ? (
        <div className="flex justify-center">
          <Button
            variant="secondary"
            isLoading={query.isFetchingNextPage}
            onClick={() => void query.fetchNextPage()}
          >
            Load more
          </Button>
        </div>
      ) : null}
    </div>
  );
}
