"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  cn,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ArrowRight, Users } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import {
  joinCommunity,
  leaveCommunity,
  type Community,
} from "@/lib/api/community";
import { communityKeys } from "@/lib/query-keys";
import { IconChip } from "@/components/shared/icon-chip";
import { SearchBar } from "@/components/shared/search-bar";
import { useCommunities } from "./use-communities";

const DELAYS = [
  "motion-safe:[animation-delay:80ms]",
  "motion-safe:[animation-delay:160ms]",
  "motion-safe:[animation-delay:240ms]",
  "motion-safe:[animation-delay:320ms]",
] as const;

/** Curated spaces you can join. Membership is what scopes the Posts feed and
 * the People tab, so joining is the primary action on every card. */
export function GroupsTab() {
  const queryClient = useQueryClient();
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [busyId, setBusyId] = useState<string | null>(null);

  useEffect(() => {
    const handle = window.setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => window.clearTimeout(handle);
  }, [searchInput]);

  const { query, communities } = useCommunities({ search });

  const membershipMutation = useMutation({
    mutationFn: (community: Community) =>
      community.is_member
        ? leaveCommunity(community.id)
        : joinCommunity(community.id),
    onMutate: (community: Community) => setBusyId(community.id),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: communityKeys.all }),
    onSettled: () => setBusyId(null),
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="max-w-md">
        <SearchBar
          id="community-group-search"
          value={searchInput}
          onChange={setSearchInput}
          label="Search groups"
          placeholder="Search groups by name or topic"
        />
      </div>

      {query.isError ? (
        <ErrorState
          description="We couldn't load the group directory."
          onRetry={() => void query.refetch()}
        />
      ) : query.isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <Skeleton className="h-44 rounded-lg" />
          <Skeleton className="h-44 rounded-lg" />
          <Skeleton className="h-44 rounded-lg" />
        </div>
      ) : communities.length === 0 ? (
        <EmptyState
          icon={Users}
          title={
            search === "" ? "No groups yet" : "No groups match that search"
          }
          description={
            search === ""
              ? "Curated groups appear here as they are published."
              : "Try a shorter phrase, or clear the search to see every group."
          }
        />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {communities.map((community, index) => (
              <Card
                key={community.id}
                className={cn(
                  "h-full transition-colors hover:border-border-strong motion-safe:animate-fade-up",
                  DELAYS[index % DELAYS.length],
                )}
              >
                <CardContent className="flex h-full flex-col gap-3 p-5">
                  <div className="flex items-start gap-3">
                    <IconChip icon={Users} accent="community" size="lg" />
                    <div className="min-w-0">
                      <p className="flex flex-wrap items-center gap-2 font-semibold text-text-primary">
                        {community.name}
                        {community.is_member ? (
                          <Badge variant="success">Joined</Badge>
                        ) : null}
                      </p>
                      {community.topic ? (
                        <p className="text-caption text-text-muted">
                          {community.topic}
                        </p>
                      ) : null}
                    </div>
                  </div>

                  <p className="line-clamp-3 text-body text-text-secondary">
                    {community.summary}
                  </p>

                  <div className="mt-auto flex items-center justify-between gap-2 pt-1">
                    <Link
                      href={`/community/groups/${community.id}`}
                      className="inline-flex items-center gap-1 text-caption font-medium text-brand-primary hover:underline"
                    >
                      Open group
                      <ArrowRight aria-hidden="true" className="size-3.5" />
                    </Link>
                    <Button
                      variant={community.is_member ? "ghost" : "secondary"}
                      size="sm"
                      isLoading={busyId === community.id}
                      onClick={() => membershipMutation.mutate(community)}
                    >
                      {community.is_member ? "Leave" : "Join"}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>

          {query.hasNextPage ? (
            <div className="flex justify-center">
              <Button
                variant="secondary"
                isLoading={query.isFetchingNextPage}
                onClick={() => void query.fetchNextPage()}
              >
                Load more groups
              </Button>
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}
