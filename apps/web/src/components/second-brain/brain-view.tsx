"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Select,
  Skeleton,
} from "@educonnect/ui";
import { useInfiniteQuery, useQuery } from "@tanstack/react-query";
import {
  Brain,
  FileText,
  FolderOpen,
  Link2,
  Search,
  StickyNote,
} from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";

import {
  listCollections,
  searchKnowledge,
  type KnowledgeSourceType,
} from "@/lib/api/second-brain";
import { brainKeys } from "@/lib/query-keys";

const SOURCE_ICON: Record<KnowledgeSourceType, typeof FileText> = {
  resource: FileText,
  link: Link2,
  none: StickyNote,
};

export function BrainView() {
  const [search, setSearch] = useState("");
  const [debounced, setDebounced] = useState("");
  const [sourceType, setSourceType] = useState<KnowledgeSourceType | "">("");
  const [collectionId, setCollectionId] = useState("");

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(search.trim()), 300);

    return () => window.clearTimeout(timer);
  }, [search]);

  const params = useMemo(
    () => ({
      search: debounced === "" ? undefined : debounced,
      sourceType: sourceType === "" ? undefined : sourceType,
      collectionId: collectionId === "" ? undefined : collectionId,
    }),
    [debounced, sourceType, collectionId],
  );

  const itemsQuery = useInfiniteQuery({
    queryKey: brainKeys.search(params),
    queryFn: ({ pageParam }) =>
      searchKnowledge({ ...params, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const collectionsQuery = useQuery({
    queryKey: brainKeys.collections(),
    queryFn: () => listCollections({ perPage: 50 }),
    staleTime: 60_000,
  });

  const items = useMemo(
    () => (itemsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [itemsQuery.data],
  );
  const collections = collectionsQuery.data?.data ?? [];
  const total = itemsQuery.data?.pages[0]?.meta.summary?.total;

  return (
    <div className="space-y-4">
      <header className="relative min-h-44 overflow-hidden rounded-xl border border-border-default lg:min-h-52">
        <Image
          src="/marketing/shelf-books.jpg"
          alt=""
          fill
          priority
          sizes="(max-width: 1024px) 100vw, 1100px"
          className="object-cover object-center"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-r from-bg-canvas/95 via-bg-canvas/75 to-bg-canvas/25"
        />
        <div className="relative flex max-w-xl flex-col gap-3 p-6 lg:p-8">
          <h1 className="text-h2 text-text-primary">Second Brain</h1>
          <p className="text-body-lg text-text-secondary">
            Everything you've saved, searchable by title, summary, author, or
            venue — each item keeps its original source.
          </p>
        </div>
      </header>

      <div className="grid gap-4 xl:grid-cols-[16rem_minmax(0,1fr)] xl:items-start">
        {/* Collections rail */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FolderOpen
                aria-hidden="true"
                className="size-5 text-brand-primary"
              />
              Collections
            </CardTitle>
          </CardHeader>
          <CardContent>
            {collectionsQuery.isPending ? (
              <div className="space-y-2">
                <Skeleton className="h-8 rounded-md" />
                <Skeleton className="h-8 rounded-md" />
              </div>
            ) : collections.length === 0 ? (
              <p className="text-caption text-text-muted">
                Group items into collections as your library grows.
              </p>
            ) : (
              <ul className="space-y-1">
                <li>
                  <button
                    type="button"
                    aria-pressed={collectionId === ""}
                    onClick={() => setCollectionId("")}
                    className={railClass(collectionId === "")}
                  >
                    All items
                  </button>
                </li>
                {collections.map((collection) => (
                  <li key={collection.id}>
                    <button
                      type="button"
                      aria-pressed={collectionId === collection.id}
                      onClick={() => setCollectionId(collection.id)}
                      className={railClass(collectionId === collection.id)}
                    >
                      <span className="min-w-0 flex-1 truncate">
                        {collection.name}
                      </span>
                      <span className="text-caption text-text-muted tabular-nums">
                        {collection.item_count}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardContent className="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
              <FormField label="Search">
                {(control) => (
                  <span className="relative block">
                    <Search
                      aria-hidden="true"
                      className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
                    />
                    <Input
                      {...control}
                      value={search}
                      onChange={(event) => setSearch(event.target.value)}
                      placeholder="Search your knowledge…"
                      className="pl-9"
                    />
                  </span>
                )}
              </FormField>
              <FormField label="Source">
                {(control) => (
                  <Select
                    {...control}
                    value={sourceType}
                    onChange={(event) =>
                      setSourceType(
                        event.target.value as KnowledgeSourceType | "",
                      )
                    }
                  >
                    <option value="">Any source</option>
                    <option value="resource">From a file</option>
                    <option value="link">From a link</option>
                    <option value="none">No source</option>
                  </Select>
                )}
              </FormField>
            </CardContent>
          </Card>

          {itemsQuery.isError ? (
            <ErrorState
              title="Search couldn't run"
              onRetry={() => void itemsQuery.refetch()}
            />
          ) : itemsQuery.isPending ? (
            <div className="space-y-2">
              <Skeleton className="h-24 rounded-lg" />
              <Skeleton className="h-24 rounded-lg" />
            </div>
          ) : items.length === 0 ? (
            <Card>
              <CardContent className="py-10">
                <EmptyState
                  icon={Brain}
                  title={
                    debounced !== "" || sourceType !== "" || collectionId !== ""
                      ? "Nothing matches"
                      : "Your Second Brain is empty"
                  }
                  description={
                    debounced !== "" || sourceType !== "" || collectionId !== ""
                      ? "Try a different search or clear the filters."
                      : "Confirm suggestions in Smart Intake, or add items to start building your searchable knowledge base."
                  }
                />
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {total !== undefined ? (
                <p className="text-caption text-text-muted">
                  {total} {total === 1 ? "item" : "items"}
                </p>
              ) : null}

              {items.map((item) => {
                const Icon = SOURCE_ICON[item.source.type];

                return (
                  <Link
                    key={item.id}
                    href={`/second-brain/${item.id}`}
                    className="block rounded-lg border border-border-subtle bg-bg-surface p-4 transition-colors hover:border-border-strong"
                  >
                    <div className="flex items-start gap-3">
                      <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md bg-bg-interactive">
                        <Icon
                          aria-hidden="true"
                          className="size-4 text-brand-primary"
                        />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-body font-medium text-text-primary">
                          {item.title}
                        </p>
                        {item.summary ? (
                          <p className="mt-0.5 line-clamp-2 text-body text-text-secondary">
                            {item.summary}
                          </p>
                        ) : null}
                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                          {item.citation.authors ? (
                            <span className="text-caption text-text-muted">
                              {item.citation.authors}
                              {item.citation.published_year
                                ? ` · ${item.citation.published_year}`
                                : ""}
                            </span>
                          ) : null}
                          {item.tags.slice(0, 4).map((tag) => (
                            <Badge key={tag} variant="neutral">
                              {tag}
                            </Badge>
                          ))}
                        </div>
                      </div>
                    </div>
                  </Link>
                );
              })}

              {itemsQuery.hasNextPage ? (
                <div className="flex justify-center">
                  <Button
                    variant="secondary"
                    isLoading={itemsQuery.isFetchingNextPage}
                    loadingLabel="Loading more"
                    onClick={() => void itemsQuery.fetchNextPage()}
                  >
                    Load more
                  </Button>
                </div>
              ) : null}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function railClass(active: boolean): string {
  return cn(
    "flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-body transition-colors focus-visible:outline-2 focus-visible:outline-brand-focus",
    active
      ? "bg-bg-interactive text-brand-primary"
      : "text-text-secondary hover:bg-bg-subtle",
  );
}
