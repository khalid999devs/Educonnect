"use client";

import {
  Alert,
  Badge,
  Button,
  buttonClasses,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  ErrorState,
  Skeleton,
  Spinner,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  ArrowRight,
  Check,
  FolderOpen,
  Maximize2,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { IntakeDetail } from "@/components/intake/intake-detail";
import { IntakeReview } from "@/components/intake/intake-review";
import { IconChip } from "@/components/shared/icon-chip";
import {
  ACTIVE_COURSE_LIST_PARAMS,
  fetchAllActiveCourses,
} from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import {
  cancelIntakeItem,
  confirmIntake,
  getIntakeItem,
  isProcessing,
  listIntakeSuggestions,
  retryIntakeItem,
  type ConfirmDecision,
} from "@/lib/api/intake";
import {
  getKnowledgeItem,
  setKnowledgePurpose,
  type KnowledgePurpose,
} from "@/lib/api/second-brain";
import { getResource, updateResource } from "@/lib/api/resources";
import {
  brainKeys,
  courseKeys,
  intakeKeys,
  resourceKeys,
} from "@/lib/query-keys";
import { type CaptureResult } from "./capture-panel";
import { DirectoryPicker, type DirectorySelection } from "./directory-picker";
import { PurposePicker } from "./purpose-picker";

/** The intake pipeline's own poll cadence. Everything downstream of it (the
 * advisory purpose route) matches, so the whole flow ticks in step. */
const POLL_MS = 2500;

/** How long to wait for the advisory purpose before letting the student pick
 * unaided. Purpose routing is best-effort: when it fails, the item keeps a
 * null purpose and the student simply chooses. Waiting forever would turn an
 * optional convenience into a blocking dependency. */
const PURPOSE_WAIT_MS = 20_000;

type Step = "capture" | "process" | "purpose" | "file" | "done";

const STEP_ORDER: Step[] = ["capture", "process", "purpose", "file", "done"];

const STEP_LABELS: Record<Step, string> = {
  capture: "Capture",
  process: "Read and review",
  purpose: "Purpose",
  file: "File it",
  done: "Open it",
};

function StepRail({ step }: { step: Step }) {
  const current = STEP_ORDER.indexOf(step);

  return (
    <ol className="flex flex-wrap items-center gap-x-1.5 gap-y-2">
      {STEP_ORDER.map((entry, index) => {
        const done = index < current;
        const active = index === current;

        return (
          <li key={entry} className="flex items-center gap-1.5">
            <span
              className={cn(
                "flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-caption",
                active
                  ? "border-status-research/40 bg-status-research/15 text-status-research"
                  : done
                    ? "border-status-success/40 text-status-success"
                    : "border-border-default text-text-muted",
              )}
            >
              {done ? (
                <Check aria-hidden="true" className="size-3.5" />
              ) : (
                <span className="tabular-nums">{index + 1}</span>
              )}
              {STEP_LABELS[entry]}
            </span>
            {index < STEP_ORDER.length - 1 ? (
              <span aria-hidden="true" className="text-text-muted">
                ·
              </span>
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}

/**
 * The capture journey once something has been captured: confirm what it
 * produced -> confirm its purpose -> file it -> open the focused workspace.
 *
 * The capture step itself lives in the sidebar (`CapturePanel`) so it is always
 * one action away; this component takes over the main area only once a capture
 * exists, because reviewing it needs the full width. `onExit` returns to the
 * library, where the capture panel is ready for the next one.
 *
 * There are no dialogs anywhere in this flow. Every step renders in place and
 * every step is reversible up to the point where the student confirms.
 *
 * `IntakeDetail` and `IntakeReview` are reused verbatim as props-driven
 * children: this component supplies the data and the callbacks, and does not
 * fork a single line of the intake UI.
 */
export function CaptureFlow({
  capture,
  onExit,
}: {
  capture: CaptureResult;
  onExit: () => void;
}) {
  const queryClient = useQueryClient();
  const [purpose, setPurpose] = useState<KnowledgePurpose | null>(null);
  const [directory, setDirectory] = useState<DirectorySelection>(null);
  const [step, setStep] = useState<Step>("process");
  const [purposeDeadline, setPurposeDeadline] = useState<number | null>(null);
  const [waitedForPurpose, setWaitedForPurpose] = useState(false);

  const intakeItemId = capture.intakeItemId;

  const itemQuery = useQuery({
    queryKey: intakeKeys.item(intakeItemId),
    queryFn: () => getIntakeItem(intakeItemId),
    refetchInterval: (query) => {
      const state = query.state.data?.state;

      return state && isProcessing(state) ? POLL_MS : false;
    },
  });

  const item = itemQuery.data ?? null;
  const reviewReady = item?.state === "awaiting_review";

  const suggestionsQuery = useQuery({
    queryKey: intakeKeys.suggestions(intakeItemId),
    queryFn: () => listIntakeSuggestions(intakeItemId),
    enabled:
      reviewReady || item?.state === "saved" || item?.state === "confirmed",
  });

  const coursesQuery = useQuery({
    queryKey: courseKeys.list(ACTIVE_COURSE_LIST_PARAMS),
    queryFn: fetchAllActiveCourses,
    staleTime: 5 * 60_000,
  });

  /** The knowledge item a confirmed suggestion created. It is the subject of
   * every step after review: purpose, filing, and the workspace itself. */
  const knowledgeItemId =
    suggestionsQuery.data?.find(
      (suggestion) => suggestion.created_knowledge_item_id !== null,
    )?.created_knowledge_item_id ?? null;

  const knowledgeQuery = useQuery({
    queryKey: brainKeys.item(knowledgeItemId ?? ""),
    queryFn: () => getKnowledgeItem(knowledgeItemId as string),
    enabled: knowledgeItemId !== null,
    refetchInterval: (query) => {
      const routed = query.state.data?.purpose ?? null;
      const waiting =
        purposeDeadline !== null && Date.now() < purposeDeadline && !routed;

      return waiting ? POLL_MS : false;
    },
  });

  const knowledge = knowledgeQuery.data ?? null;

  /** The resource this capture actually produced, if any. A file capture
   * uploads one up front; a link capture only has one if the student applied
   * a resource suggestion during review. When there is neither, there is
   * genuinely nothing to file and the step says so instead of pretending. */
  const createdResourceId =
    capture.resourceId ??
    suggestionsQuery.data?.find(
      (suggestion) => suggestion.created_resource_id !== null,
    )?.created_resource_id ??
    null;

  const invalidateIntake = () => {
    void queryClient.invalidateQueries({ queryKey: intakeKeys.all });
  };

  const actionMutation = useMutation({
    mutationFn: ({
      action,
      itemId,
    }: {
      action: "cancel" | "retry";
      itemId: string;
    }) =>
      action === "cancel" ? cancelIntakeItem(itemId) : retryIntakeItem(itemId),
    onSuccess: invalidateIntake,
  });

  const confirmMutation = useMutation({
    mutationFn: ({
      itemId,
      decisions,
    }: {
      itemId: string;
      decisions: ConfirmDecision[];
    }) => confirmIntake(itemId, decisions),
    onSuccess: () => {
      invalidateIntake();
      void queryClient.invalidateQueries({ queryKey: brainKeys.all });
      void queryClient.invalidateQueries({ queryKey: resourceKeys.all });
      setPurposeDeadline(Date.now() + PURPOSE_WAIT_MS);
      setStep("purpose");
    },
  });

  const purposeMutation = useMutation({
    mutationFn: (next: KnowledgePurpose) => {
      if (!knowledge) {
        throw new Error("The captured item is still loading.");
      }

      return setKnowledgePurpose(knowledge, next);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: brainKeys.all });
      setStep("file");
    },
  });

  const fileMutation = useMutation({
    mutationFn: async (courseId: DirectorySelection) => {
      if (createdResourceId === null) {
        return;
      }

      /* PUT /resources/{resource} replaces every field it accepts, so the
         resource is re-read and its current values echoed back. Sending only
         course_id would blank the title, description, and topic. */
      const resource = await getResource(createdResourceId);
      const shared = {
        expected_version: resource.version,
        title: resource.title,
        description: resource.description,
        topic: resource.topic,
        course_id: courseId,
      };

      await updateResource(
        resource.id,
        resource.kind === "link"
          ? { kind: "link", ...shared, url: resource.url ?? "" }
          : { kind: "file", ...shared },
      );
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: resourceKeys.all });
      setStep("done");
    },
  });

  /** Adopt the routed purpose the moment it arrives, unless the student has
   * already made a choice. Their choice always wins: the route is advisory. */
  useEffect(() => {
    if (knowledge?.purpose && purpose === null) {
      setPurpose(knowledge.purpose);
    }
  }, [knowledge?.purpose, purpose]);

  useEffect(() => {
    if (purposeDeadline === null || waitedForPurpose) {
      return;
    }

    const remaining = purposeDeadline - Date.now();
    const timer = window.setTimeout(
      () => setWaitedForPurpose(true),
      Math.max(0, remaining),
    );

    return () => window.clearTimeout(timer);
  }, [purposeDeadline, waitedForPurpose]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <StepRail step={step} />
        {step !== "done" ? (
          <Button variant="ghost" size="sm" onClick={onExit}>
            <ArrowLeft aria-hidden="true" className="size-4" />
            Back to your library
          </Button>
        ) : null}
      </div>

      {step === "process" ? (
        itemQuery.isPending ? (
          <Skeleton className="h-72 rounded-lg" />
        ) : itemQuery.isError ? (
          <ErrorState
            title="We lost track of this capture"
            onRetry={() => void itemQuery.refetch()}
          />
        ) : item ? (
          <IntakeDetail
            item={item}
            busy={actionMutation.isPending}
            onCancel={() =>
              actionMutation.mutate({ action: "cancel", itemId: item.id })
            }
            onRetry={() =>
              actionMutation.mutate({ action: "retry", itemId: item.id })
            }
          >
            {reviewReady ? (
              suggestionsQuery.isPending ? (
                <Skeleton className="h-40 rounded-md" />
              ) : suggestionsQuery.isError ? (
                <ErrorState
                  title="Suggestions couldn't load"
                  onRetry={() => void suggestionsQuery.refetch()}
                />
              ) : suggestionsQuery.data && suggestionsQuery.data.length > 0 ? (
                <IntakeReview
                  suggestions={suggestionsQuery.data}
                  courses={coursesQuery.data ?? []}
                  busy={confirmMutation.isPending}
                  error={
                    confirmMutation.error instanceof ApiError &&
                    confirmMutation.error.status === 409
                      ? "This capture changed. Reload and review again."
                      : (confirmMutation.error?.message ?? null)
                  }
                  onConfirm={(decisions) =>
                    confirmMutation.mutate({ itemId: item.id, decisions })
                  }
                />
              ) : (
                <Alert variant="info" title="Nothing to review">
                  <p>
                    No suggestions came back, so there is nothing to file from
                    this one. Discard it, or head back and capture something
                    else.
                  </p>
                  <div className="mt-3">
                    <Button
                      variant="secondary"
                      size="sm"
                      isLoading={actionMutation.isPending}
                      loadingLabel="Discarding"
                      onClick={() =>
                        actionMutation.mutate(
                          { action: "cancel", itemId: item.id },
                          { onSuccess: onExit },
                        )
                      }
                    >
                      Discard capture
                    </Button>
                  </div>
                </Alert>
              )
            ) : null}
          </IntakeDetail>
        ) : null
      ) : null}

      {step === "purpose" ? (
        <Card className="motion-safe:animate-fade-up">
          <CardHeader>
            <CardTitle className="flex items-center gap-2.5">
              <IconChip icon={ArrowRight} accent="secondBrain" />
              What is this for?
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {knowledgeItemId === null ? (
              <Alert variant="info" title="Nothing was saved to your brain">
                This capture did not create a knowledge item, so there is no
                purpose to set. Everything you applied is already in its own
                section.
              </Alert>
            ) : knowledgeQuery.isPending ? (
              <Skeleton className="h-32 rounded-md" />
            ) : knowledgeQuery.isError ? (
              <ErrorState
                title="The saved item couldn't load"
                onRetry={() => void knowledgeQuery.refetch()}
              />
            ) : (
              <>
                {knowledge?.purpose ? (
                  <p className="flex items-center gap-1.5 text-caption text-text-muted">
                    <Badge variant="ai">Pre-selected</Badge>
                    This is a suggestion, not a decision. Change it with one
                    click.
                  </p>
                ) : !waitedForPurpose ? (
                  <p className="flex items-center gap-2 text-caption text-text-muted">
                    <Spinner size="sm" className="text-status-research" />
                    Working out the most likely purpose. Pick one now if you
                    already know.
                  </p>
                ) : (
                  <p className="text-caption text-text-muted">
                    No suggestion this time. Pick the one that fits.
                  </p>
                )}

                <PurposePicker
                  label="What is this for"
                  value={purpose}
                  suggested={knowledge?.purpose ?? null}
                  onChange={setPurpose}
                  disabled={purposeMutation.isPending}
                />

                {purposeMutation.error ? (
                  <Alert variant="error" title="Couldn't save that purpose">
                    {purposeMutation.error.message}
                  </Alert>
                ) : null}
              </>
            )}

            <div className="flex flex-wrap gap-2">
              <Button
                isLoading={purposeMutation.isPending}
                loadingLabel="Saving"
                disabled={purpose === null || knowledgeItemId === null}
                onClick={() => purpose && purposeMutation.mutate(purpose)}
              >
                Confirm purpose
              </Button>
              <Button variant="ghost" onClick={() => setStep("file")}>
                Skip for now
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {step === "file" ? (
        <Card className="motion-safe:animate-fade-up">
          <CardHeader>
            <CardTitle className="flex items-center gap-2.5">
              <IconChip icon={FolderOpen} accent="resources" />
              Where should it live?
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {createdResourceId === null ? (
              <Alert variant="info" title="Nothing to file">
                This capture did not produce a stored file, so there is no
                directory to put it in. It is searchable in your Second Brain
                either way.
              </Alert>
            ) : (
              <DirectoryPicker
                value={directory}
                onChange={setDirectory}
                disabled={fileMutation.isPending}
              />
            )}

            {fileMutation.error ? (
              <Alert variant="error" title="Couldn't file it there">
                {fileMutation.error.message}
              </Alert>
            ) : null}

            <div className="flex flex-wrap gap-2">
              <Button
                isLoading={fileMutation.isPending}
                loadingLabel="Filing"
                onClick={() => fileMutation.mutate(directory)}
              >
                {createdResourceId === null ? "Continue" : "File it here"}
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {step === "done" ? (
        <Card className="motion-safe:animate-fade-up">
          <CardContent className="flex flex-wrap items-center justify-between gap-3 py-5">
            <div className="flex min-w-0 items-center gap-3">
              <IconChip icon={Check} accent="community" size="lg" />
              <div className="min-w-0">
                <p className="truncate text-body font-medium text-text-primary">
                  {capture.label}
                </p>
                <p className="text-caption text-text-muted">
                  Saved, filed, and ready to work through.
                </p>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              {knowledgeItemId !== null ? (
                <Link
                  href={`/second-brain/${knowledgeItemId}/workspace?intake=${intakeItemId}`}
                  className={buttonClasses({ glow: true })}
                >
                  <Maximize2 aria-hidden="true" className="size-4" />
                  Open focused workspace
                </Link>
              ) : null}
              <Button variant="secondary" onClick={onExit}>
                Back to your library
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
