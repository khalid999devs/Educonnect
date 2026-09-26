"use client";

import {
  Badge,
  Button,
  cn,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useInfiniteQuery } from "@tanstack/react-query";
import { BadgeCheck, UserRound, Users } from "lucide-react";
import { useMemo, useState } from "react";

import { listCommunityMembers } from "@/lib/api/community";
import { formatRelativeTime } from "@/lib/format";
import { communityKeys } from "@/lib/query-keys";
import { useCommunities } from "./use-communities";

/**
 * People you actually share a space with.
 *
 * Scope is deliberate and documented: there is no friend or follow graph
 * anywhere in the schema, so "People" means the members of the groups you have
 * joined, read one group at a time through the membership-gated
 * `GET /communities/{community}/members` endpoint.
 */
export function PeopleTab() {
  const { query: communitiesQuery, joined } = useCommunities({ loadAll: true });
  const [selectedId, setSelectedId] = useState<string | null>(null);

  const activeId = useMemo(() => {
    if (selectedId && joined.some((group) => group.id === selectedId)) {
      return selectedId;
    }

    return joined[0]?.id ?? null;
  }, [joined, selectedId]);

  const membersQuery = useInfiniteQuery({
    queryKey: communityKeys.members(activeId ?? "none"),
    queryFn: ({ pageParam }) =>
      listCommunityMembers(activeId ?? "", { perPage: 30, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
    enabled: activeId !== null,
  });

  const members = useMemo(
    () => (membersQuery.data?.pages ?? []).flatMap((page) => page.data),
    [membersQuery.data],
  );

  if (communitiesQuery.isError) {
    return (
      <ErrorState
        description="We couldn't load your groups."
        onRetry={() => void communitiesQuery.refetch()}
      />
    );
  }

  if (communitiesQuery.isPending) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-10 w-80 rounded-full" />
        <Skeleton className="h-64 rounded-lg" />
      </div>
    );
  }

  if (joined.length === 0) {
    return (
      <EmptyState
        icon={Users}
        title="You haven't joined a group yet"
        description="People here are the members of the groups you join. Open the Groups tab and join a space to see who else is there."
      />
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div
        role="group"
        aria-label="Pick a group"
        className="flex flex-wrap gap-2 motion-safe:animate-fade-up"
      >
        {joined.map((group) => {
          const selected = group.id === activeId;

          return (
            <button
              key={group.id}
              type="button"
              aria-pressed={selected}
              onClick={() => setSelectedId(group.id)}
              className={cn(
                "rounded-full border px-4 py-2 text-body font-medium transition-colors",
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                selected
                  ? "border-status-success/40 bg-status-success/12 text-text-primary"
                  : "border-border-default bg-bg-surface text-text-secondary hover:border-border-strong hover:text-text-primary",
              )}
            >
              {group.name}
            </button>
          );
        })}
      </div>

      {membersQuery.isError ? (
        <ErrorState
          description="We couldn't load the members of this group."
          onRetry={() => void membersQuery.refetch()}
        />
      ) : membersQuery.isPending ? (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <Skeleton className="h-20 rounded-lg" />
          <Skeleton className="h-20 rounded-lg" />
          <Skeleton className="h-20 rounded-lg" />
        </div>
      ) : members.length === 0 ? (
        <EmptyState
          icon={UserRound}
          title="No one else is here yet"
          description="You are the first member of this group. Post something and others will find it."
        />
      ) : (
        <div className="flex flex-col gap-4">
          <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {members.map((member) => (
              <li
                key={member.id}
                className="flex items-start gap-3 rounded-lg border border-border-subtle bg-bg-surface p-4 transition-colors hover:border-border-strong motion-safe:animate-fade-up"
              >
                <span
                  aria-hidden="true"
                  className="flex size-11 shrink-0 items-center justify-center rounded-full bg-status-success/12 text-body font-semibold text-status-success"
                >
                  {member.name.trim().charAt(0).toUpperCase() || "?"}
                </span>
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-1.5 font-medium text-text-primary">
                    {member.name}
                    {member.is_verified_mentor ? (
                      <Badge variant="success">
                        <BadgeCheck aria-hidden="true" className="size-3.5" />
                        Verified mentor
                      </Badge>
                    ) : null}
                    {member.role === "moderator" ? (
                      <Badge variant="info">Moderator</Badge>
                    ) : null}
                  </p>
                  {member.joined_at ? (
                    <p className="text-caption tabular-nums text-text-muted">
                      Joined {formatRelativeTime(member.joined_at)}
                    </p>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>

          {membersQuery.hasNextPage ? (
            <div className="flex justify-center">
              <Button
                variant="secondary"
                isLoading={membersQuery.isFetchingNextPage}
                onClick={() => void membersQuery.fetchNextPage()}
              >
                Load more people
              </Button>
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}
