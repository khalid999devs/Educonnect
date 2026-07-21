"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Spinner,
} from "@educonnect/ui";
import { RotateCcw } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import {
  readExamQuestions,
  readStudyOutput,
  type StudyArtifact,
} from "@/lib/api/study";
import { STUDY_ACTIONS } from "../purpose";

export type ArtifactCardProps = {
  artifact: StudyArtifact;
  onRegenerate: () => void;
  busy: boolean;
};

/**
 * One generated artifact, in whichever of its four states it is actually in.
 *
 * The hard rule this component exists to enforce: a failed artifact renders an
 * honest failure and NEVER fabricated content. The database guarantees a
 * non-ready artifact carries no payload, and every branch here reads `status`
 * before it reads `payload`, so there is no path that can present a guess as a
 * result. A ready artifact whose payload does not match its kind is treated as
 * unavailable for the same reason.
 *
 * All rendered text is model output derived from an untrusted document. It
 * goes through JSX text interpolation and is therefore escaped: an injection
 * payload surfaces as visible characters, never as markup.
 */
export function ArtifactCard({
  artifact,
  onRegenerate,
  busy,
}: ArtifactCardProps) {
  const action = STUDY_ACTIONS[artifact.kind];
  const pending = artifact.status === "queued" || artifact.status === "running";
  const output = readStudyOutput(artifact);
  const exam = readExamQuestions(artifact);

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-start justify-between gap-2">
        <CardTitle className="flex items-center gap-2.5 text-h4">
          <IconChip icon={action.icon} accent="study" size="sm" />
          {action.label}
        </CardTitle>
        <Badge
          variant={
            artifact.status === "ready"
              ? "success"
              : artifact.status === "failed"
                ? "error"
                : "info"
          }
        >
          {artifact.status === "ready"
            ? "Ready"
            : artifact.status === "failed"
              ? "Failed"
              : artifact.status === "running"
                ? "Working"
                : "Queued"}
        </Badge>
      </CardHeader>

      <CardContent className="space-y-3">
        {pending ? (
          <p className="flex items-center gap-2 text-body text-text-muted">
            <Spinner size="sm" className="text-status-ai" />
            Working through the document. This stays here while it runs.
          </p>
        ) : null}

        {artifact.status === "failed" ? (
          <>
            <Alert variant="error" title="This couldn't be generated">
              {artifact.failure_reason ??
                "The assistant could not complete this. Nothing was made up in its place."}
            </Alert>
            <Button
              variant="secondary"
              size="sm"
              disabled={busy}
              onClick={onRegenerate}
            >
              <RotateCcw aria-hidden="true" className="size-4" />
              <span className="ml-1.5">Try again</span>
            </Button>
          </>
        ) : null}

        {artifact.status === "ready" && !output && !exam ? (
          <Alert variant="warning" title="This result can't be displayed">
            The generated content did not match the expected shape, so it is not
            being shown rather than shown partially.
          </Alert>
        ) : null}

        {output ? (
          <div className="space-y-3">
            <h4 className="text-h4 text-text-primary">{output.title}</h4>
            <p className="whitespace-pre-wrap text-body text-text-secondary">
              {output.overview}
            </p>

            {output.sections.map((section, index) => (
              <section
                key={`${index}-${section.heading}`}
                className="space-y-1"
              >
                <h5 className="text-label text-text-primary">
                  {section.heading}
                </h5>
                <p className="whitespace-pre-wrap text-body text-text-secondary">
                  {section.body}
                </p>
              </section>
            ))}

            {output.key_points.length > 0 ? (
              <div className="space-y-1.5">
                <h5 className="text-label text-text-primary">Key points</h5>
                <ul className="space-y-1">
                  {output.key_points.map((point, index) => (
                    <li
                      key={`${index}-${point.slice(0, 24)}`}
                      className="flex gap-2 text-body text-text-secondary"
                    >
                      <span aria-hidden="true" className="text-status-ai">
                        •
                      </span>
                      {point}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        ) : null}

        {exam ? (
          <ol className="space-y-4">
            {exam.questions.map((question, index) => (
              <li key={`${index}-${question.prompt.slice(0, 24)}`}>
                <p className="text-body font-medium text-text-primary">
                  <span className="tabular-nums">{index + 1}. </span>
                  {question.prompt}
                </p>
                {question.options && question.options.length > 0 ? (
                  <ul className="mt-1.5 space-y-1">
                    {question.options.map((option, optionIndex) => (
                      <li
                        key={`${optionIndex}-${option.slice(0, 24)}`}
                        className="text-body text-text-secondary"
                      >
                        {option}
                      </li>
                    ))}
                  </ul>
                ) : null}
                <details className="mt-1.5 rounded-md border border-border-subtle">
                  <summary className="cursor-pointer px-3 py-1.5 text-caption font-medium text-text-muted">
                    Show the answer
                  </summary>
                  <div className="space-y-1 px-3 pb-2.5">
                    <p className="text-body text-text-primary">
                      {question.answer}
                    </p>
                    {question.explanation ? (
                      <p className="text-caption text-text-secondary">
                        {question.explanation}
                      </p>
                    ) : null}
                  </div>
                </details>
              </li>
            ))}
          </ol>
        ) : null}

        {artifact.status === "ready" ? (
          <p className="text-caption text-text-muted">
            {artifact.disclaimer}
            {artifact.model ? (
              <>
                {" "}
                Generated by {artifact.model}
                {artifact.latency_ms !== null ? (
                  <>
                    {" "}
                    in{" "}
                    <span className="tabular-nums">
                      {artifact.latency_ms}
                    </span>{" "}
                    ms
                  </>
                ) : null}
                .
              </>
            ) : null}
          </p>
        ) : null}
      </CardContent>
    </Card>
  );
}
