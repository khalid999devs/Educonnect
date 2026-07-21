"use client";

import { Alert, cn, EmptyState, ErrorState, Skeleton } from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Sparkles, Wand2 } from "lucide-react";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { SECTION_ACCENT } from "@/components/shell/section-accent";
import type { KnowledgePurpose } from "@/lib/api/second-brain";
import {
  listStudyArtifacts,
  requestStudyGeneration,
  type StudyArtifact,
  type StudyArtifactKind,
} from "@/lib/api/study";
import { studyKeys } from "@/lib/query-keys";
import { actionsForPurpose, STUDY_ACTIONS } from "../purpose";
import { ArtifactCard } from "./artifact-card";

export type ActionsPaneProps = {
  itemId: string;
  purpose: KnowledgePurpose | null;
};

/** The intake pipeline's cadence, matched exactly. ADR-0021 rejected SSE, so
 * generation is queue-plus-poll rather than streamed. */
const POLL_MS = 2500;

function hasPending(artifacts: StudyArtifact[]): boolean {
  return artifacts.some((artifact) => artifact.is_pending);
}

/**
 * Ready actions, chosen by the item's purpose.
 *
 * `POST /study/{item}/generations` always returns 202 with a queued artifact.
 * UNIQUE(knowledge_item_id, kind) makes that artifact both the cache and the
 * dedupe key, so pressing an action twice returns the same artifact instead of
 * billing a provider again, and a failed artifact is re-queued in place.
 *
 * The list is polled while anything is queued or running and stops the moment
 * nothing is - no timers left running behind a finished workspace.
 */
export function ActionsPane({ itemId, purpose }: ActionsPaneProps) {
  const queryClient = useQueryClient();
  const [lastRequested, setLastRequested] = useState<StudyArtifactKind | null>(
    null,
  );

  const artifactsQuery = useQuery({
    queryKey: studyKeys.artifacts({ itemId, sort: "-created_at" }),
    queryFn: () =>
      listStudyArtifacts({ itemId, sort: "-created_at", perPage: 20 }),
    refetchInterval: (query) => {
      const artifacts = query.state.data?.data ?? [];

      return hasPending(artifacts) ? POLL_MS : false;
    },
  });

  const generateMutation = useMutation({
    mutationFn: (kind: StudyArtifactKind) =>
      requestStudyGeneration(itemId, kind),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: studyKeys.all });
    },
  });

  const offered = actionsForPurpose(purpose);
  const artifacts = artifactsQuery.data?.data ?? [];
  const byKind = new Map(
    artifacts.map((artifact) => [artifact.kind, artifact]),
  );
  const accent = SECTION_ACCENT.study;

  return (
    <div className="flex h-full min-h-0 flex-col">
      <header className="flex shrink-0 items-center gap-2.5 border-b border-border-subtle px-4 py-3 sm:px-5">
        <IconChip icon={Wand2} accent="study" />
        <div className="min-w-0">
          <h2 className="text-h4 text-text-primary">Ready actions</h2>
          <p className="truncate text-caption text-text-muted">
            Chosen for what you said this is for.
          </p>
        </div>
      </header>

      <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5">
        <div className="grid gap-2 sm:grid-cols-2">
          {offered.map((kind) => {
            const action = STUDY_ACTIONS[kind];
            const existing = byKind.get(kind);
            const running = existing?.is_pending ?? false;
            const Icon = action.icon;

            return (
              <button
                key={kind}
                type="button"
                disabled={running || generateMutation.isPending}
                onClick={() => {
                  setLastRequested(kind);
                  generateMutation.mutate(kind);
                }}
                className={cn(
                  "flex items-start gap-2.5 rounded-lg border p-3 text-left transition-colors",
                  "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                  "disabled:cursor-not-allowed disabled:opacity-60",
                  "border-border-subtle bg-bg-surface hover:border-border-strong",
                )}
              >
                <span
                  className={cn(
                    "flex size-8 shrink-0 items-center justify-center rounded-md",
                    accent.chip,
                  )}
                >
                  <Icon
                    aria-hidden="true"
                    className={cn("size-4", accent.icon)}
                  />
                </span>
                <span className="min-w-0">
                  <span className="block text-body font-medium text-text-primary">
                    {action.label}
                  </span>
                  <span className="block text-caption text-text-secondary">
                    {running ? "Working on it…" : action.description}
                  </span>
                </span>
              </button>
            );
          })}
        </div>

        {generateMutation.error ? (
          <Alert variant="error" title="That couldn't be started">
            {generateMutation.error.message}
          </Alert>
        ) : null}

        {artifactsQuery.isPending ? (
          <div className="space-y-2">
            <Skeleton className="h-28 rounded-lg" />
            <Skeleton className="h-28 rounded-lg" />
          </div>
        ) : artifactsQuery.isError ? (
          <ErrorState
            title="Your generated material couldn't load"
            onRetry={() => void artifactsQuery.refetch()}
          />
        ) : artifacts.length === 0 ? (
          <EmptyState
            icon={Sparkles}
            title="Nothing generated yet"
            description="Pick an action above. Results appear here and stay with this item."
          />
        ) : (
          <div className="space-y-3">
            {artifacts.map((artifact) => (
              <ArtifactCard
                key={artifact.id}
                artifact={artifact}
                busy={generateMutation.isPending}
                onRegenerate={() => {
                  setLastRequested(artifact.kind);
                  generateMutation.mutate(artifact.kind);
                }}
              />
            ))}
          </div>
        )}

        {lastRequested !== null ? (
          <p aria-live="polite" className="sr-only">
            {STUDY_ACTIONS[lastRequested].label} requested.
          </p>
        ) : null}
      </div>
    </div>
  );
}
