"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Spinner,
} from "@educonnect/ui";
import { CircleAlert, Info, RotateCcw } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import {
  readExamQuestions,
  readStudyOutput,
  type StudyArtifact,
} from "@/lib/api/study";

import { ExamQuestions } from "./exam-questions";
import { STUDY_KINDS } from "./study-intents";
import { StudyOutput } from "./study-output";

const STATUS_LABEL: Record<StudyArtifact["status"], string> = {
  queued: "Waiting to start",
  running: "Working through your material",
  ready: "Ready",
  failed: "Did not finish",
};

const STATUS_VARIANT: Record<
  StudyArtifact["status"],
  "neutral" | "ai" | "success" | "error"
> = {
  queued: "neutral",
  running: "ai",
  ready: "success",
  failed: "error",
};

/**
 * One artifact, in whatever state it is actually in.
 *
 * The status branch is exhaustive and comes first. There is deliberately no
 * path on which a `failed` or pending artifact reaches a content renderer: a
 * failure is shown as a failure, with its reason and a retry, and never
 * replaced with generated-looking material. An artifact that is ready but
 * whose payload does not match its kind is treated the same way, because a
 * half-parsed result is indistinguishable from a fabricated one.
 */
export function StudyResult({
  artifact,
  sourceTitle,
  onRetry,
  retryPending,
}: {
  artifact: StudyArtifact;
  sourceTitle: string;
  onRetry: () => void;
  retryPending: boolean;
}) {
  const kind = STUDY_KINDS[artifact.kind];
  const output = readStudyOutput(artifact);
  const exam = readExamQuestions(artifact);
  const unreadable =
    artifact.status === "ready" &&
    (artifact.kind === "exam_questions" ? exam === null : output === null);

  return (
    <Card className="motion-safe:animate-fade-up">
      <CardHeader className="flex flex-wrap items-start justify-between gap-3">
        <CardTitle className="flex min-w-0 items-center gap-2.5">
          <IconChip icon={kind.icon} accent="study" size="md" />
          <span className="min-w-0">
            <span className="block truncate">{kind.label}</span>
            <span className="block truncate text-caption text-text-muted">
              {sourceTitle}
            </span>
          </span>
        </CardTitle>
        <Badge variant={STATUS_VARIANT[artifact.status]}>
          {STATUS_LABEL[artifact.status]}
        </Badge>
      </CardHeader>

      <CardContent className="space-y-4">
        {artifact.is_pending ? (
          <div
            role="status"
            aria-live="polite"
            className="flex items-center gap-3 rounded-lg border border-border-default bg-bg-surface p-4"
          >
            <Spinner size="sm" />
            <div>
              <p className="text-label text-text-primary">
                {STATUS_LABEL[artifact.status]}
              </p>
              <p className="text-caption text-text-muted">
                This runs in the background. You can leave this open; the result
                appears here as soon as it is done.
              </p>
            </div>
          </div>
        ) : artifact.status === "failed" ? (
          <div
            role="alert"
            className="space-y-3 rounded-lg border border-status-error/30 bg-status-error/5 p-4"
          >
            <div className="flex items-start gap-2.5">
              <CircleAlert
                aria-hidden="true"
                className="mt-0.5 size-5 shrink-0 text-status-error"
              />
              <div className="space-y-1">
                <p className="text-label text-text-primary">
                  Nothing was generated
                </p>
                <p className="text-body text-text-secondary">
                  {artifact.failure_reason ??
                    "The generation did not complete and no reason was recorded."}
                </p>
                <p className="text-caption text-text-muted">
                  There is no fallback for study material on purpose. A made-up
                  summary or a wrong exam answer is worse than none, so this
                  reports the failure instead of filling the gap.
                </p>
              </div>
            </div>
            <Button
              variant="secondary"
              size="sm"
              disabled={retryPending}
              onClick={onRetry}
            >
              <RotateCcw aria-hidden="true" className="size-4" />
              {retryPending ? "Starting..." : "Try again"}
            </Button>
          </div>
        ) : unreadable ? (
          <div
            role="alert"
            className="space-y-3 rounded-lg border border-status-error/30 bg-status-error/5 p-4"
          >
            <p className="text-label text-text-primary">
              This result could not be read
            </p>
            <p className="text-body text-text-secondary">
              The stored result does not match the shape this section expects,
              so none of it is shown. Generating it again is safe.
            </p>
            <Button
              variant="secondary"
              size="sm"
              disabled={retryPending}
              onClick={onRetry}
            >
              <RotateCcw aria-hidden="true" className="size-4" />
              Generate again
            </Button>
          </div>
        ) : artifact.kind === "exam_questions" && exam ? (
          <ExamQuestions questions={exam.questions} sourceTitle={sourceTitle} />
        ) : output ? (
          <StudyOutput payload={output} />
        ) : null}

        <StudyProvenance artifact={artifact} />
      </CardContent>
    </Card>
  );
}

/** Honest sourcing: which model produced this, how long it took, and the
 * standing disclaimer the API ships with every artifact. */
function StudyProvenance({ artifact }: { artifact: StudyArtifact }) {
  const facts: string[] = [];

  if (artifact.provider) {
    facts.push(artifact.provider);
  }
  if (artifact.model) {
    facts.push(artifact.model);
  }
  if (artifact.latency_ms !== null) {
    facts.push(`${(artifact.latency_ms / 1000).toFixed(1)}s`);
  }
  facts.push(`schema ${artifact.schema_version}`);

  return (
    <div className="space-y-1.5 border-t border-border-subtle pt-3">
      <p className="flex items-start gap-1.5 text-caption text-text-secondary">
        <Info aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
        {artifact.disclaimer}
      </p>
      <p className="text-caption tabular-nums text-text-muted">
        {facts.join(" · ")}
      </p>
    </div>
  );
}
