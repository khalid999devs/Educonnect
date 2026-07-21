"use client";

import {
  Alert,
  Button,
  buttonClasses,
  cn,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useInfiniteQuery, useMutation } from "@tanstack/react-query";
import { Check, FileText, Inbox, Sparkles } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { SearchBar } from "@/components/shared/search-bar";
import { createFileIntake } from "@/lib/api/intake";
import { listResources, type Resource } from "@/lib/api/resources";
import { searchKnowledge } from "@/lib/api/second-brain";
import { resourceKeys } from "@/lib/query-keys";
import { type CaptureResult } from "./capture-panel";

/**
 * "From a source": the third way into the Second Brain, alongside dropping a
 * file and pasting a link. It brings in something already uploaded to Resources
 * rather than uploading it again.
 *
 * A flat, searchable list of ready files across every directory - not a
 * directory drill-down - keeps this focused on the one thing it does. Only
 * `kind: "file"` and `fileStatus: "ready"` are listed, because a file still
 * uploading or a bare link has nothing to read yet.
 *
 * Picking a file resolves it honestly: if it is already in the Second Brain
 * (confirmed on the source resource id, never the title alone), it offers to
 * open the existing item instead of creating a duplicate; otherwise it starts a
 * fresh capture and hands the result up so the capture flow can take over.
 */
export function SourcePicker({
  onCaptured,
}: {
  onCaptured: (result: CaptureResult) => void;
}) {
  const [search, setSearch] = useState("");
  const [debounced, setDebounced] = useState("");
  const [resolvingId, setResolvingId] = useState<string | null>(null);
  const [existing, setExisting] = useState<{
    itemId: string;
    title: string;
  } | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(search.trim()), 300);

    return () => window.clearTimeout(timer);
  }, [search]);

  const resourcesQuery = useInfiniteQuery({
    queryKey: resourceKeys.list({ scope: "brain-source", search: debounced }),
    queryFn: ({ pageParam }) =>
      listResources({
        search: debounced === "" ? undefined : debounced,
        kind: "file",
        fileStatus: "ready",
        sort: "-updated_at",
        perPage: 15,
        cursor: pageParam,
      }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const resolveMutation = useMutation({
    mutationFn: async (resource: Resource) => {
      /* Already in the Second Brain? The knowledge API has no resource_id
         filter, so this searches by title and confirms the match on the source
         resource id - the title alone is never trusted to identify the row. */
      const found = await searchKnowledge({
        search: resource.title.slice(0, 120),
        sourceType: "resource",
        perPage: 50,
      });
      const match = found.data.find(
        (candidate) => candidate.source.resource?.id === resource.id,
      );

      if (match) {
        return {
          kind: "existing" as const,
          itemId: match.id,
          title: match.title,
        };
      }

      const intake = await createFileIntake(resource.id);

      return {
        kind: "captured" as const,
        result: {
          intakeItemId: intake.id,
          resourceId: resource.id,
          label: resource.title,
        } satisfies CaptureResult,
      };
    },
    onSuccess: (outcome) => {
      if (outcome.kind === "existing") {
        setExisting({ itemId: outcome.itemId, title: outcome.title });
      } else {
        onCaptured(outcome.result);
      }

      setResolvingId(null);
    },
    onError: () => setResolvingId(null),
  });

  const resources = (resourcesQuery.data?.pages ?? []).flatMap(
    (page) => page.data,
  );

  if (existing) {
    return (
      <div className="space-y-3 rounded-lg border border-status-research/30 bg-bg-surface p-4 motion-safe:animate-fade-up">
        <div className="flex items-center gap-2.5">
          <IconChip icon={Check} accent="secondBrain" size="sm" />
          <p className="text-label text-text-primary">
            Already in your Second Brain
          </p>
        </div>
        <p className="text-caption text-text-muted">
          &ldquo;{existing.title}&rdquo; has already been brought in. Open it to
          keep working, or pick another file.
        </p>
        <div className="flex flex-wrap gap-2">
          <Link
            href={`/second-brain/${existing.itemId}`}
            className={buttonClasses({ size: "sm" })}
          >
            Open it
          </Link>
          <Button variant="ghost" size="sm" onClick={() => setExisting(null)}>
            Pick another file
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <SearchBar
        value={search}
        onChange={setSearch}
        label="Search your files"
        placeholder="Search your files…"
      />

      {resolveMutation.isError ? (
        <Alert variant="error" title="That file couldn't be prepared">
          The lookup didn&rsquo;t complete. Try that file again.
        </Alert>
      ) : null}

      {resourcesQuery.isPending ? (
        <div className="space-y-2">
          <Skeleton className="h-14 rounded-lg" />
          <Skeleton className="h-14 rounded-lg" />
          <Skeleton className="h-14 rounded-lg" />
        </div>
      ) : resourcesQuery.isError ? (
        <ErrorState
          title="Your files couldn't load"
          onRetry={() => void resourcesQuery.refetch()}
        />
      ) : resources.length === 0 ? (
        <EmptyState
          icon={Inbox}
          title={debounced === "" ? "No files yet" : "No files match"}
          description={
            debounced === ""
              ? "Upload something in Resources, or drop a file above to bring it in."
              : "Try a different search, or drop a file above."
          }
        />
      ) : (
        <>
          <ul className="space-y-2">
            {resources.map((resource) => {
              const busy =
                resolveMutation.isPending && resolvingId === resource.id;

              return (
                <li key={resource.id}>
                  <button
                    type="button"
                    disabled={resolveMutation.isPending}
                    onClick={() => {
                      setResolvingId(resource.id);
                      resolveMutation.mutate(resource);
                    }}
                    className={cn(
                      "flex w-full items-center gap-3 rounded-lg border border-border-default bg-bg-surface p-3 text-left transition-colors",
                      "hover:border-border-strong disabled:opacity-60",
                      "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                    )}
                  >
                    <IconChip icon={FileText} accent="secondBrain" size="md" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-label text-text-primary">
                        {resource.title}
                      </span>
                      {resource.topic ? (
                        <span className="block truncate text-caption text-text-muted">
                          {resource.topic}
                        </span>
                      ) : null}
                    </span>
                    <span className="flex shrink-0 items-center gap-1.5 text-caption text-text-muted">
                      {busy ? (
                        "Bringing in…"
                      ) : (
                        <>
                          <Sparkles aria-hidden="true" className="size-4" />
                          Bring in
                        </>
                      )}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>

          {resourcesQuery.hasNextPage ? (
            <Button
              variant="secondary"
              size="sm"
              isLoading={resourcesQuery.isFetchingNextPage}
              loadingLabel="Loading"
              onClick={() => void resourcesQuery.fetchNextPage()}
            >
              Load more
            </Button>
          ) : null}
        </>
      )}
    </div>
  );
}
