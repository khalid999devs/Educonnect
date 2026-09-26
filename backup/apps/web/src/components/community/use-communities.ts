"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { useEffect, useMemo } from "react";

import { listCommunities, type Community } from "@/lib/api/community";
import { communityKeys } from "@/lib/query-keys";

const PER_PAGE = 30;

/**
 * Cursor-paginated community list shared by the Posts, Groups, and People tabs.
 *
 * `loadAll` exists because two callers need the *complete* set rather than a
 * page: the composer's "post to" picker and the People tab's group scope. A
 * bare `perPage` fetch silently loses every group past the first page, so the
 * hook drains the cursor instead of pretending one page is the whole roster.
 */
export function useCommunities({
  search,
  loadAll = false,
}: { search?: string; loadAll?: boolean } = {}) {
  const params = { search: search === "" ? undefined : search };

  const query = useInfiniteQuery({
    queryKey: communityKeys.communities({ ...params, per_page: PER_PAGE }),
    queryFn: ({ pageParam }) =>
      listCommunities({ ...params, perPage: PER_PAGE, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const { hasNextPage, isFetchingNextPage, fetchNextPage } = query;

  useEffect(() => {
    if (loadAll && hasNextPage && !isFetchingNextPage) {
      void fetchNextPage();
    }
  }, [loadAll, hasNextPage, isFetchingNextPage, fetchNextPage]);

  const communities: Community[] = useMemo(
    () => (query.data?.pages ?? []).flatMap((page) => page.data),
    [query.data],
  );

  const joined = useMemo(
    () => communities.filter((community) => community.is_member),
    [communities],
  );

  return { query, communities, joined };
}
