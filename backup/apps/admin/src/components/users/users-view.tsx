"use client";

import { Badge, Button, ErrorState, Select, Skeleton } from "@educonnect/ui";
import { useInfiniteQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

import { listUsers, ROLE_KEYS, type UserFilters } from "@/lib/api/admin-users";
import { formatDateTime, humanizeKey } from "@/lib/format";
import { userKeys } from "@/lib/query-keys";

const STATUS_BADGE: Record<string, "success" | "error"> = {
  active: "success",
  suspended: "error",
};

export function UsersView() {
  const [draft, setDraft] = useState({ search: "", role: "", status: "" });
  const [filters, setFilters] = useState<UserFilters>({});

  const query = useInfiniteQuery({
    queryKey: userKeys.list(filters),
    queryFn: ({ pageParam }) => listUsers({ ...filters, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const users = query.data?.pages.flatMap((page) => page.items) ?? [];

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Users</h1>
        <p className="text-body text-text-secondary">
          Search accounts, review roles and status, and suspend or reactivate
          with a recorded reason.
        </p>
      </header>

      <form
        className="flex flex-wrap items-end gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          setFilters({
            search: draft.search.trim() || undefined,
            role: draft.role || undefined,
            status: draft.status || undefined,
          });
        }}
      >
        <label className="flex-1 min-w-56">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Search name or email
          </span>
          <input
            type="search"
            value={draft.search}
            onChange={(event) =>
              setDraft((prev) => ({ ...prev, search: event.target.value }))
            }
            className="w-full rounded-md border border-border-default bg-bg-surface px-3 py-2 text-body text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
            placeholder="e.g. priya@example.com"
          />
        </label>
        <label className="w-44">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Role
          </span>
          <Select
            value={draft.role}
            onChange={(event) =>
              setDraft((prev) => ({ ...prev, role: event.target.value }))
            }
          >
            <option value="">All roles</option>
            {ROLE_KEYS.map((role) => (
              <option key={role} value={role}>
                {humanizeKey(role)}
              </option>
            ))}
          </Select>
        </label>
        <label className="w-44">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Status
          </span>
          <Select
            value={draft.status}
            onChange={(event) =>
              setDraft((prev) => ({ ...prev, status: event.target.value }))
            }
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </Select>
        </label>
        <Button type="submit" variant="secondary">
          Apply
        </Button>
      </form>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 5 }).map((_, index) => (
            <Skeleton key={index} className="h-14 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load users"
          description="The user directory could not be loaded. Retry in a moment."
          onRetry={() => void query.refetch()}
        />
      ) : users.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No users match these filters.
        </p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border-subtle">
          <table className="w-full border-collapse text-left text-body">
            <thead>
              <tr className="border-b border-border-subtle bg-bg-surface text-caption uppercase tracking-wide text-text-muted">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Roles</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Last sign-in</th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr
                  key={user.id}
                  className="border-b border-border-subtle last:border-0 hover:bg-bg-surface"
                >
                  <td className="px-4 py-3">
                    <Link
                      href={`/users/${user.id}`}
                      className="font-medium text-brand-primary hover:underline"
                    >
                      {user.name}
                    </Link>
                    <span className="block text-caption text-text-muted">
                      {user.email}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className="flex flex-wrap gap-1">
                      {user.roles.map((role) => (
                        <Badge key={role}>{humanizeKey(role)}</Badge>
                      ))}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={STATUS_BADGE[user.status] ?? "neutral"}>
                      {humanizeKey(user.status)}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-text-secondary">
                    {formatDateTime(user.last_login_at)}
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
