"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  cn,
  EmptyState,
  ErrorState,
  Select,
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { Brain, FileText, FolderOpen, Link2, StickyNote } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { PageCover } from "@/components/shared/page-cover";
import { SavedFilterChip } from "@/components/shared/saved-filter-chip";
import { SearchBar } from "@/components/shared/search-bar";
import { SECTION_ACCENT } from "@/components/shell/section-accent";
import {
  listCollections,
  saveKnowledgeItem,
  searchKnowledge,
  unsaveKnowledgeItem,
  type KnowledgeSourceType,
} from "@/lib/api/second-brain";
import { brainKeys } from "@/lib/query-keys";
import { CaptureFlow } from "./capture-flow";
import { CapturePanel, type CaptureResult } from "./capture-panel";
import {
  isPurposeFilter,
  PURPOSES,
  purposeDescriptor,
  type PurposeFilter,
} from "./purpose";
import { SaveToggle } from "./save-toggle";

const SOURCE_ICON: Record<KnowledgeSourceType, typeof FileText> = {
  resource: FileText,
  link: Link2,
  none: StickyNote,
};

/**
 * Second Brain: the student's main companion.
 *
 * Research is folded in here as `purpose=research` rather than living in its
 * own section, so `/second-brain?purpose=research` is a first-class URL and
 * the old Research bookmarks keep working after the tab is removed.
 *
 * The purpose filter is mirrored into the URL both ways: deep links restore
 * the right filter, and changing the filter updates the address bar without
 * pushing a history entry per click.
 */
export function BrainView() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const queryClient = useQueryClient();

  const urlPurpose = searchParams.get("purpose");
  const purposeFilter: PurposeFilter =
    urlPurpose !== null && isPurposeFilter(urlPurpose) ? urlPurpose : "all";
  /* Server-side and URL-synced: `?saved=true` is a linkable saved view, and the
     flag is never filtered client-side. */
  const savedOnly = searchParams.get("saved") === "true";

  const [search, setSearch] = useState("");
  const [debounced, setDebounced] = useState("");
  const [sourceType, setSourceType] = useState<KnowledgeSourceType | "">("");
  const [collectionId, setCollectionId] = useState("");
  const [activeCapture, setActiveCapture] = useState<CaptureResult | null>(
    null,
  );
  const [busyId, setBusyId] = useState<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(search.trim()), 300);

    return () => window.clearTimeout(timer);
  }, [search]);

  const setPurposeFilter = useCallback(
    (next: PurposeFilter) => {
      const params = new URLSearchParams(searchParams.toString());

      if (next === "all") {
        params.delete("purpose");
      } else {
        params.set("purpose", next);
      }

      const query = params.toString();
      router.replace(query === "" ? pathname : `${pathname}?${query}`, {
        scroll: false,
      });
    },
    [pathname, router, searchParams],
  );

  const toggleSaved = useCallback(() => {
    const params = new URLSearchParams(searchParams.toString());

    if (savedOnly) {
      params.delete("saved");
    } else {
      params.set("saved", "true");
    }

    const query = params.toString();
    router.replace(query === "" ? pathname : `${pathname}?${query}`, {
      scroll: false,
    });
  }, [pathname, router, savedOnly, searchParams]);

  const params = useMemo(
    () => ({
      search: debounced === "" ? undefined : debounced,
      sourceType: sourceType === "" ? undefined : sourceType,
      collectionId: collectionId === "" ? undefined : collectionId,
      purpose: purposeFilter === "all" ? undefined : purposeFilter,
      saved: savedOnly ? true : undefined,
    }),
    [debounced, sourceType, collectionId, purposeFilter, savedOnly],
  );

  /* One item's bookmark. On success the whole brain family is invalidated so
     every window - including a saved-only view - stays truthful. */
  const saveMutation = useMutation({
    mutationFn: async ({ id, saved }: { id: string; saved: boolean }) => {
      if (saved) {
        await unsaveKnowledgeItem(id);
      } else {
        await saveKnowledgeItem(id);
      }
    },
    onMutate: ({ id }) => setBusyId(id),
    onSettled: () => setBusyId(null),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: brainKeys.all });
    },
  });

  const itemsQuery = useInfiniteQuery({
    queryKey: brainKeys.search(params),
    queryFn: ({ pageParam }) =>
      searchKnowledge({ ...params, perPage: 30, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const collectionsQuery = useInfiniteQuery({
    queryKey: brainKeys.collections(),
    queryFn: ({ pageParam }) =>
      listCollections({ perPage: 50, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
    staleTime: 60_000,
  });

  const items = useMemo(
    () => (itemsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [itemsQuery.data],
  );
  const collections = useMemo(
    () => (collectionsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [collectionsQuery.data],
  );
  const total = itemsQuery.data?.pages[0]?.meta.summary?.total;
  const filtered =
    debounced !== "" ||
    sourceType !== "" ||
    collectionId !== "" ||
    purposeFilter !== "all" ||
    savedOnly;

  /* The library browse and filter only earn their space once there is something
     to browse. A brand-new, empty library shows just the capture card. A filter
     that happens to match nothing still counts, so the control never disappears
     on its own selection. */
  const hasLibraryContent = items.length > 0 || filtered;
  const showBrowse =
    collections.length > 0 || collectionsQuery.isError || hasLibraryContent;

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/shelf-books.jpg"
        headingLevel={1}
        tall
        priority
        title="Everything you've captured, ready to work through"
        subtitle="Drop in a file or a link, say what it's for, and open it in a focused workspace with the text, a chat that knows it, and the right next actions."
      />

      {activeCapture ? (
        <div className="motion-safe:animate-fade-up">
          <CaptureFlow
            capture={activeCapture}
            onExit={() => setActiveCapture(null)}
          />
        </div>
      ) : (
        <>
          <PurposeFilterBar value={purposeFilter} onChange={setPurposeFilter} />

          <div className="grid gap-4 xl:grid-cols-[27rem_minmax(0,1fr)] xl:items-start">
            <Card className="motion-safe:animate-fade-up">
              <CapturePanel onCaptured={setActiveCapture} />

              {showBrowse ? (
                <div className="mt-5 space-y-4 border-t border-border-subtle pt-5">
                  {collections.length > 0 ? (
                    <div className="space-y-2">
                      <div className="flex items-center gap-2">
                        <IconChip
                          icon={FolderOpen}
                          accent="secondBrain"
                          size="sm"
                        />
                        <p className="text-label text-text-primary">
                          Collections
                        </p>
                      </div>
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
                              className={railClass(
                                collectionId === collection.id,
                              )}
                            >
                              <span className="min-w-0 flex-1 truncate">
                                {collection.name}
                              </span>
                              <span className="text-caption tabular-nums text-text-muted">
                                {collection.item_count}
                              </span>
                            </button>
                          </li>
                        ))}
                      </ul>
                      {collectionsQuery.hasNextPage ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          isLoading={collectionsQuery.isFetchingNextPage}
                          loadingLabel="Loading"
                          onClick={() => void collectionsQuery.fetchNextPage()}
                        >
                          Show more
                        </Button>
                      ) : null}
                    </div>
                  ) : collectionsQuery.isError ? (
                    <ErrorState
                      title="Collections couldn't load"
                      onRetry={() => void collectionsQuery.refetch()}
                    />
                  ) : null}

                  {hasLibraryContent ? (
                    <div>
                      <label
                        htmlFor="brain-source-filter"
                        className="block text-label text-text-secondary"
                      >
                        Show
                      </label>
                      <Select
                        id="brain-source-filter"
                        className="mt-1.5"
                        value={sourceType}
                        onChange={(event) =>
                          setSourceType(
                            event.target.value as KnowledgeSourceType | "",
                          )
                        }
                      >
                        <option value="">Everything</option>
                        <option value="resource">Files</option>
                        <option value="link">Links</option>
                        <option value="none">Notes</option>
                      </Select>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </Card>

            <div className="space-y-3 motion-safe:animate-fade-up motion-safe:[animation-delay:160ms]">
              <div className="flex items-center gap-2">
                <div className="flex-1">
                  <SearchBar
                    value={search}
                    onChange={setSearch}
                    label="Search your Second Brain"
                    placeholder="Search by title, summary, author, or venue…"
                  />
                </div>
                <SavedFilterChip
                  tone="bookmark"
                  active={savedOnly}
                  onToggle={toggleSaved}
                  describes="knowledge items"
                  className="h-10 shrink-0 rounded-md"
                />
              </div>

              {itemsQuery.isError ? (
                <ErrorState
                  title="Search couldn't run"
                  onRetry={() => void itemsQuery.refetch()}
                />
              ) : itemsQuery.isPending ? (
                <div className="space-y-2">
                  <Skeleton className="h-24 rounded-lg" />
                  <Skeleton className="h-24 rounded-lg" />
                  <Skeleton className="h-24 rounded-lg" />
                </div>
              ) : items.length === 0 ? (
                <Card>
                  <CardContent className="py-10">
                    <EmptyState
                      icon={Brain}
                      title={
                        savedOnly
                          ? "Nothing saved yet"
                          : filtered
                            ? "Nothing matches"
                            : "Your Second Brain is empty"
                      }
                      description={
                        savedOnly
                          ? "Use the bookmark on any item's card to save it, and it shows up here."
                          : filtered
                            ? "Try a different search, or clear the filters."
                            : "Capture a file or a link above. We read it, you say what it's for, and it lands here ready to work through."
                      }
                      action={
                        savedOnly ? (
                          <Button variant="secondary" onClick={toggleSaved}>
                            Browse everything
                          </Button>
                        ) : null
                      }
                    />
                  </CardContent>
                </Card>
              ) : (
                <>
                  {total !== undefined ? (
                    <p className="text-caption tabular-nums text-text-muted">
                      {total} {total === 1 ? "item" : "items"}
                    </p>
                  ) : null}

                  {items.map((item) => {
                    const Icon = SOURCE_ICON[item.source.type];
                    const purpose = purposeDescriptor(item.purpose);
                    const accent =
                      SECTION_ACCENT[purpose?.accent ?? "secondBrain"];

                    return (
                      <div
                        key={item.id}
                        className="relative rounded-lg border border-border-subtle bg-bg-surface p-4 transition-colors hover:border-border-strong focus-within:border-border-strong"
                      >
                        {/* Stretched link: the card is one click target, and the
                        bookmark toggle sits on top of it with its own pointer
                        events so a save never navigates. */}
                        <Link
                          href={`/second-brain/${item.id}`}
                          aria-label={item.title}
                          className="absolute inset-0 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
                        />
                        <div className="pointer-events-none relative flex items-start gap-3">
                          <span
                            className={cn(
                              "mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md",
                              accent.chip,
                            )}
                          >
                            <Icon
                              aria-hidden="true"
                              className={cn("size-4", accent.icon)}
                            />
                          </span>
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                              <p className="min-w-0 flex-1 truncate text-body font-medium text-text-primary">
                                {item.title}
                              </p>
                              {purpose ? (
                                <Badge variant="research">
                                  {purpose.label}
                                </Badge>
                              ) : null}
                            </div>
                            {item.summary ? (
                              <p className="mt-0.5 line-clamp-2 text-body text-text-secondary">
                                {item.summary}
                              </p>
                            ) : null}
                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                              {item.citation.authors ? (
                                <span className="text-caption text-text-muted">
                                  {item.citation.authors}
                                  {item.citation.published_year ? (
                                    <span className="tabular-nums">
                                      {` · ${item.citation.published_year}`}
                                    </span>
                                  ) : null}
                                </span>
                              ) : null}
                              {item.tags.slice(0, 4).map((tag) => (
                                <Badge key={tag} variant="neutral">
                                  {tag}
                                </Badge>
                              ))}
                            </div>
                          </div>
                          <SaveToggle
                            className="pointer-events-auto"
                            saved={item.saved}
                            busy={busyId === item.id}
                            onToggle={() =>
                              saveMutation.mutate({
                                id: item.id,
                                saved: item.saved,
                              })
                            }
                          />
                        </div>
                      </div>
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
                </>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

function PurposeFilterBar({
  value,
  onChange,
}: {
  value: PurposeFilter;
  onChange: (next: PurposeFilter) => void;
}) {
  const options: { value: PurposeFilter; label: string }[] = [
    { value: "all", label: "Everything" },
    ...PURPOSES.map((purpose) => ({
      value: purpose.value as PurposeFilter,
      label: purpose.label,
    })),
    { value: "none", label: "No purpose yet" },
  ];

  return (
    <div
      role="group"
      aria-label="Filter by purpose"
      className="flex flex-wrap gap-1.5"
    >
      {options.map((option) => {
        const descriptor = purposeDescriptor(
          option.value === "all" || option.value === "none"
            ? null
            : option.value,
        );
        const accent = SECTION_ACCENT[descriptor?.accent ?? "secondBrain"];
        const active = option.value === value;

        return (
          <button
            key={option.value}
            type="button"
            aria-pressed={active}
            onClick={() => onChange(option.value)}
            className={cn(
              "flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-caption transition-colors",
              "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
              active
                ? cn("bg-bg-elevated text-text-primary", accent.ring)
                : "border-border-subtle text-text-secondary hover:border-border-strong",
            )}
          >
            {descriptor ? (
              <descriptor.icon
                aria-hidden="true"
                className={cn("size-3.5", accent.icon)}
              />
            ) : null}
            {option.label}
          </button>
        );
      })}
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
