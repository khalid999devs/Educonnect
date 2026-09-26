"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  cn,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { History, Sparkles } from "lucide-react";
import { useEffect, useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { ApiError } from "@/lib/api/http";
import {
  getStudyArtifact,
  listStudyArtifacts,
  requestStudyGeneration,
  type StudyArtifact,
  type StudyArtifactKind,
} from "@/lib/api/study";
import { studyKeys } from "@/lib/query-keys";

import { StudyResult } from "./study-result";
import {
  orderedKindsForIntent,
  STUDY_KINDS,
  type StudyIntent,
} from "./study-intents";

/** Matches the intake pipeline's cadence, which this UI already trains
 * students to expect. ADR-0021 rejected SSE, so generation is queue-plus-poll. */
const POLL_INTERVAL_MS = 2500;

/**
 * The ready-made actions for the chosen intent, the live result, and this
 * item's earlier results.
 *
 * Polling contract: the single-artifact query is enabled only while an
 * artifact is selected and re-fetches only while `is_pending` is true, so it
 * stops the moment the artifact reaches `ready` or `failed`. React Query owns
 * the timer, so unmounting this panel disposes it - there is no interval of
 * our own that could outlive the component.
 */
export function StudyGeneration({
  intent,
  source,
}: {
  intent: StudyIntent;
  source: { itemId: string; title: string };
}) {
  const queryClient = useQueryClient();
  const [activeId, setActiveId] = useState<string | null>(null);
  const [activeKind, setActiveKind] = useState<StudyArtifactKind | null>(null);

  const historyQuery = useInfiniteQuery({
    queryKey: studyKeys.artifacts({
      itemId: source.itemId,
      sort: "-created_at",
    }),
    queryFn: ({ pageParam }) =>
      listStudyArtifacts({
        itemId: source.itemId,
        sort: "-created_at",
        perPage: 20,
        cursor: pageParam,
      }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const artifactQuery = useQuery({
    queryKey: studyKeys.artifact(activeId ?? ""),
    queryFn: () => getStudyArtifact(activeId as string),
    enabled: activeId !== null,
    refetchInterval: (query) =>
      query.state.data?.is_pending === true ? POLL_INTERVAL_MS : false,
  });

  /* When the actively-polled artifact settles out of pending, the list is stale:
     it was invalidated at request time while this artifact was still queued, and
     the single-artifact poll is keyed separately, so the badges and history rows
     keep reading "running" until an unrelated refocus. Refresh the list family
     the moment the poll reaches ready or failed. */
  const activePending = artifactQuery.data?.is_pending;
  useEffect(() => {
    if (activeId !== null && activePending === false) {
      void queryClient.invalidateQueries({
        queryKey: studyKeys.artifacts({
          itemId: source.itemId,
          sort: "-created_at",
        }),
      });
    }
  }, [activeId, activePending, queryClient, source.itemId]);

  const generateMutation = useMutation({
    mutationFn: (kind: StudyArtifactKind) =>
      requestStudyGeneration(source.itemId, kind),
    onSuccess: (artifact: StudyArtifact) => {
      setActiveId(artifact.id);
      setActiveKind(artifact.kind);
      queryClient.setQueryData(studyKeys.artifact(artifact.id), artifact);
      void queryClient.invalidateQueries({ queryKey: studyKeys.all });
    },
  });

  const artifacts = (historyQuery.data?.pages ?? []).flatMap(
    (page) => page.data,
  );
  const summary = historyQuery.data?.pages[0]?.meta.summary;
  const byKind = new Map(artifacts.map((entry) => [entry.kind, entry]));
  const active = artifactQuery.data ?? null;

  const generateError = generateMutation.error;
  const generateMessage =
    generateError instanceof ApiError
      ? generateError.status === 503
        ? "Study generation is unavailable right now. Nothing was generated and nothing was charged. Try again shortly."
        : generateError.message
      : generateError instanceof Error
        ? generateError.message
        : null;

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="space-y-3">
          <div className="flex items-center gap-2">
            <IconChip icon={Sparkles} accent="study" size="sm" />
            <p className="text-label text-text-primary">
              Ready-made actions for {source.title}
            </p>
          </div>

          {generateMessage ? (
            <Alert variant="error" title="Nothing was generated">
              {generateMessage}
            </Alert>
          ) : null}

          <div className="grid gap-2 sm:grid-cols-2">
            {orderedKindsForIntent(intent).map((kind, index) => {
              const config = STUDY_KINDS[kind];
              const existing = byKind.get(kind);
              const busy =
                generateMutation.isPending &&
                generateMutation.variables === kind;

              return (
                <button
                  key={kind}
                  type="button"
                  disabled={generateMutation.isPending}
                  onClick={() => {
                    if (
                      existing &&
                      !existing.is_pending &&
                      existing.status === "ready"
                    ) {
                      setActiveId(existing.id);
                      setActiveKind(existing.kind);

                      return;
                    }

                    generateMutation.mutate(kind);
                  }}
                  className={cn(
                    "flex h-full items-start gap-3 rounded-lg border bg-bg-surface p-3.5 text-left transition-colors",
                    "hover:border-border-strong disabled:opacity-60",
                    "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                    "motion-safe:animate-fade-up",
                    activeKind === kind
                      ? "border-status-ai/40"
                      : "border-border-default",
                    index === 1 ? "motion-safe:[animation-delay:80ms]" : null,
                    index === 2 ? "motion-safe:[animation-delay:160ms]" : null,
                    index === 3 ? "motion-safe:[animation-delay:240ms]" : null,
                  )}
                >
                  <IconChip icon={config.icon} accent="study" size="md" />
                  <span className="min-w-0 flex-1 space-y-1">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="text-label text-text-primary">
                        {config.label}
                      </span>
                      {existing?.status === "ready" ? (
                        <Badge variant="success">Ready</Badge>
                      ) : existing?.is_pending ? (
                        <Badge variant="ai">Running</Badge>
                      ) : existing?.status === "failed" ? (
                        <Badge variant="error">Failed</Badge>
                      ) : null}
                    </span>
                    <span className="block text-caption text-text-secondary">
                      {config.description}
                    </span>
                    <span className="block text-caption text-text-muted">
                      {busy
                        ? "Starting..."
                        : existing?.status === "ready"
                          ? "View result"
                          : config.cta}
                    </span>
                  </span>
                </button>
              );
            })}
          </div>
        </CardContent>
      </Card>

      {activeId !== null ? (
        artifactQuery.isPending ? (
          <Skeleton className="h-48 rounded-lg" />
        ) : artifactQuery.isError ? (
          <ErrorState
            title="That result could not be loaded"
            description="The generation may still be running. Try again."
            onRetry={() => void artifactQuery.refetch()}
          />
        ) : active ? (
          <StudyResult
            artifact={active}
            sourceTitle={source.title}
            retryPending={generateMutation.isPending}
            onRetry={() => generateMutation.mutate(active.kind)}
          />
        ) : null
      ) : null}

      {artifacts.length > 0 || historyQuery.isError ? (
        <Card>
          <CardContent className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <IconChip icon={History} accent="study" size="sm" />
                <p className="text-label text-text-primary">
                  Earlier results for this item
                </p>
              </div>
              {summary ? (
                <p className="text-caption tabular-nums text-text-muted">
                  {summary.total} total · {summary.ready} ready ·{" "}
                  {summary.failed} failed
                </p>
              ) : null}
            </div>

            {historyQuery.isError ? (
              <ErrorState
                title="Earlier results could not be loaded"
                onRetry={() => void historyQuery.refetch()}
              />
            ) : (
              <>
                <ul className="space-y-2">
                  {artifacts.map((artifact) => (
                    <li key={artifact.id}>
                      <button
                        type="button"
                        onClick={() => {
                          setActiveId(artifact.id);
                          setActiveKind(artifact.kind);
                        }}
                        aria-current={
                          artifact.id === activeId ? "true" : undefined
                        }
                        className={cn(
                          "flex w-full items-center gap-3 rounded-md border px-3 py-2 text-left transition-colors",
                          "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                          artifact.id === activeId
                            ? "border-status-ai/40 bg-bg-interactive"
                            : "border-border-default hover:border-border-strong",
                        )}
                      >
                        <IconChip
                          icon={STUDY_KINDS[artifact.kind].icon}
                          accent="study"
                          size="sm"
                        />
                        <span className="min-w-0 flex-1 truncate text-body text-text-primary">
                          {STUDY_KINDS[artifact.kind].label}
                        </span>
                        <span className="shrink-0 text-caption tabular-nums text-text-muted">
                          v{artifact.version}
                        </span>
                        <Badge
                          variant={
                            artifact.status === "ready"
                              ? "success"
                              : artifact.status === "failed"
                                ? "error"
                                : "ai"
                          }
                        >
                          {artifact.status}
                        </Badge>
                      </button>
                    </li>
                  ))}
                </ul>

                {historyQuery.hasNextPage ? (
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={historyQuery.isFetchingNextPage}
                    onClick={() => void historyQuery.fetchNextPage()}
                  >
                    {historyQuery.isFetchingNextPage
                      ? "Loading..."
                      : "Load older results"}
                  </Button>
                ) : null}
              </>
            )}
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
