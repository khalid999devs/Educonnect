"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  cn,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useInfiniteQuery, useMutation, useQuery } from "@tanstack/react-query";
import {
  ChevronLeft,
  FileText,
  FolderOpen,
  Inbox,
  Link2,
  Sparkles,
} from "lucide-react";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { createFileIntake } from "@/lib/api/intake";
import {
  listResourceDirectories,
  listResources,
  UNFILED_COURSE_ID,
  type Resource,
  type ResourceDirectory,
} from "@/lib/api/resources";
import { searchKnowledge } from "@/lib/api/second-brain";
import { resourceKeys } from "@/lib/query-keys";

/**
 * The exam-preparation walkthrough, and the library path for the other two
 * intents: directory, then item, then generate.
 *
 * Every step is a section of this same panel. Going back replaces the panel's
 * contents in place; nothing opens over the page.
 *
 * The last step is the honest one. Study generation reads a document through
 * its capture provenance, so a resource that was never captured has no text to
 * work from. Rather than generate something anyway, this offers to run the
 * capture right now.
 */
export function StudyLibraryPicker({
  onReady,
  onNeedsCapture,
}: {
  onReady: (item: { itemId: string; title: string }) => void;
  /** Called with the intake id when a picked resource has to be captured. */
  onNeedsCapture: (intakeId: string, resourceTitle: string) => void;
}) {
  const [directory, setDirectory] = useState<ResourceDirectory | null>(null);
  const [resolving, setResolving] = useState<Resource | null>(null);

  const directoriesQuery = useQuery({
    queryKey: resourceKeys.directories(),
    queryFn: () => listResourceDirectories(),
    staleTime: 60_000,
  });

  const courseId =
    directory === null
      ? undefined
      : directory.kind === "unfiled"
        ? UNFILED_COURSE_ID
        : (directory.course?.id ?? undefined);

  const resourcesQuery = useInfiniteQuery({
    queryKey: resourceKeys.list({ courseId: courseId ?? "", forStudy: true }),
    queryFn: ({ pageParam }) =>
      listResources({
        courseId,
        sort: "-updated_at",
        perPage: 20,
        cursor: pageParam,
      }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
    enabled: directory !== null,
  });

  /* Resolves the knowledge item that was created when this resource was
     captured. The API has no resource_id filter on knowledge items, so this
     searches by title and confirms the match on the source resource id -
     the title alone is never trusted to identify the row. */
  const resolveMutation = useMutation({
    mutationFn: async (resource: Resource) => {
      const found = await searchKnowledge({
        search: resource.title.slice(0, 120),
        sourceType: "resource",
        perPage: 50,
      });
      const match = found.data.find(
        (candidate) => candidate.source.resource?.id === resource.id,
      );

      if (match) {
        return { kind: "ready" as const, itemId: match.id, title: match.title };
      }

      const intake = await createFileIntake(resource.id);

      return { kind: "capturing" as const, intakeId: intake.id };
    },
    onSuccess: (result, resource) => {
      if (result.kind === "ready") {
        onReady({ itemId: result.itemId, title: result.title });
      } else {
        onNeedsCapture(result.intakeId, resource.title);
      }

      setResolving(null);
    },
    onError: () => setResolving(null),
  });

  const directories = directoriesQuery.data ?? [];
  const resources = (resourcesQuery.data?.pages ?? []).flatMap(
    (page) => page.data,
  );

  if (directory === null) {
    return (
      <Card>
        <CardContent className="space-y-3">
          <div className="flex items-center gap-2">
            <IconChip icon={FolderOpen} accent="study" size="sm" />
            <p className="text-label text-text-primary">
              Step 1 of 2: choose a directory
            </p>
          </div>

          {directoriesQuery.isPending ? (
            <div className="grid gap-2 sm:grid-cols-2">
              <Skeleton className="h-16 rounded-lg" />
              <Skeleton className="h-16 rounded-lg" />
              <Skeleton className="h-16 rounded-lg" />
              <Skeleton className="h-16 rounded-lg" />
            </div>
          ) : directoriesQuery.isError ? (
            <ErrorState
              title="Directories could not be loaded"
              description="Your course directories are built from your own resources. Try again."
              onRetry={() => void directoriesQuery.refetch()}
            />
          ) : directories.length === 0 ? (
            <EmptyState
              icon={Inbox}
              title="No directories yet"
              description="Course directories appear once you have resources filed against a course. Bring something in above to get started."
            />
          ) : (
            <ul className="grid gap-2 sm:grid-cols-2">
              {directories.map((entry) => {
                const label =
                  entry.kind === "unfiled"
                    ? "Unfiled"
                    : (entry.course?.title ?? "Untitled course");
                const archived = entry.course?.archive_status === "archived";

                return (
                  <li key={`${entry.kind}:${entry.course?.id ?? "unfiled"}`}>
                    <button
                      type="button"
                      onClick={() => setDirectory(entry)}
                      className={cn(
                        "flex w-full items-center gap-3 rounded-lg border border-border-default bg-bg-surface p-3 text-left transition-colors",
                        "hover:border-border-strong",
                        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                      )}
                    >
                      <IconChip
                        icon={entry.kind === "unfiled" ? Inbox : FolderOpen}
                        accent="study"
                        size="md"
                      />
                      <span className="min-w-0 flex-1">
                        <span className="flex items-center gap-2">
                          <span className="truncate text-label text-text-primary">
                            {label}
                          </span>
                          {archived ? (
                            <Badge variant="neutral">Archived</Badge>
                          ) : null}
                        </span>
                        <span className="block text-caption tabular-nums text-text-muted">
                          {entry.resource_count} item
                          {entry.resource_count === 1 ? "" : "s"}
                        </span>
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </CardContent>
      </Card>
    );
  }

  const directoryLabel =
    directory.kind === "unfiled"
      ? "Unfiled"
      : (directory.course?.title ?? "Untitled course");

  return (
    <Card>
      <CardContent className="space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setDirectory(null)}
            aria-label="Back to directories"
          >
            <ChevronLeft aria-hidden="true" className="size-4" />
            Directories
          </Button>
          <span aria-hidden="true" className="text-text-muted">
            /
          </span>
          <p className="text-label text-text-primary">{directoryLabel}</p>
        </div>

        <p className="text-caption text-text-muted">
          Step 2 of 2: choose the exact material to work from.
        </p>

        {resolveMutation.isError ? (
          <Alert variant="error" title="That item could not be prepared">
            The library lookup did not complete. Try that item again.
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
            title="That directory could not be opened"
            onRetry={() => void resourcesQuery.refetch()}
          />
        ) : resources.length === 0 ? (
          <EmptyState
            icon={FileText}
            title="Nothing filed here yet"
            description="Add material to this course from Resources, or bring something in with the capture panel above."
          />
        ) : (
          <>
            <ul className="space-y-2">
              {resources.map((resource) => {
                const busy =
                  resolveMutation.isPending && resolving?.id === resource.id;

                return (
                  <li key={resource.id}>
                    <button
                      type="button"
                      disabled={resolveMutation.isPending}
                      onClick={() => {
                        setResolving(resource);
                        resolveMutation.mutate(resource);
                      }}
                      className={cn(
                        "flex w-full items-center gap-3 rounded-lg border border-border-default bg-bg-surface p-3 text-left transition-colors",
                        "hover:border-border-strong disabled:opacity-60",
                        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                      )}
                    >
                      <IconChip
                        icon={resource.kind === "link" ? Link2 : FileText}
                        accent="study"
                        size="md"
                      />
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
                          "Preparing..."
                        ) : (
                          <>
                            <Sparkles aria-hidden="true" className="size-4" />
                            Use this
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
                disabled={resourcesQuery.isFetchingNextPage}
                onClick={() => void resourcesQuery.fetchNextPage()}
              >
                {resourcesQuery.isFetchingNextPage
                  ? "Loading..."
                  : "Load more material"}
              </Button>
            ) : null}
          </>
        )}

        <p className="text-caption text-text-muted">
          Material that has never been captured has no extracted text yet.
          Choosing it starts that capture first rather than generating from
          nothing.
        </p>
      </CardContent>
    </Card>
  );
}
