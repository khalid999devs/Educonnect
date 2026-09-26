"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  Skeleton,
  Spinner,
} from "@educonnect/ui";
import {
  ArrowRight,
  Brain,
  CalendarClock,
  Check,
  CircleCheck,
  FileText,
  FolderOpen,
  Lock,
  Sparkles,
  Target,
  UploadCloud,
} from "lucide-react";
import { useEffect } from "react";

import { useDemo } from "./demo-app";
import { ANALYSIS_STAGES, DEMO_DOCUMENTS, DEMO_PERSONA } from "./demo-data";
import { PageCover } from "./page-cover";

const STEPS = [
  { label: "Upload", sub: "Add a sample document" },
  { label: "Extract", sub: "Reads the text" },
  { label: "Organize", sub: "Dates & course signals" },
  { label: "Review", sub: "Confirm & finalize" },
] as const;

export function IntakeView() {
  const { state, dispatch } = useDemo();
  const doc = DEMO_DOCUMENTS.find((d) => d.id === state.intake.docId) ?? null;
  const { status } = state.intake;

  /* Deterministic staged analysis: fixed order, fixed cadence. */
  useEffect(() => {
    if (status !== "analyzing") {
      return;
    }

    const interval = setInterval(() => dispatch({ type: "advanceStage" }), 700);

    return () => clearInterval(interval);
  }, [status, dispatch]);

  const activeStep =
    status === "idle" || status === "selected"
      ? 0
      : status === "analyzing"
        ? state.intake.stage < 2
          ? 1
          : 2
        : 3;
  const stepDone = (index: number) =>
    status === "confirmed" ? true : index < activeStep;

  const taskCount =
    doc?.suggestions.filter((s) => s.kind === "task").length ?? 0;
  const noteCount =
    doc?.suggestions.filter((s) => s.kind === "note").length ?? 0;
  const acceptedCount = state.intake.accepted.length;

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/notebook-pens.jpg"
        eyebrow="Smart Intake"
        title={`Welcome back, ${DEMO_PERSONA.name}!`}
        subtitle="Upload a sample syllabus and let extraction propose a plan. You review and confirm every change."
      />

      {/* Stepper */}
      <ol
        aria-label="Intake steps"
        className="grid gap-2 rounded-xl border border-border-default bg-bg-surface p-3 sm:grid-cols-2 lg:grid-cols-4"
      >
        {STEPS.map((step, index) => {
          const isActive = index === activeStep && status !== "confirmed";
          const isDone = stepDone(index);

          return (
            <li
              key={step.label}
              aria-current={isActive ? "step" : undefined}
              className={cn(
                "flex items-center gap-3 rounded-lg border px-3.5 py-2.5",
                isActive
                  ? "border-brand-primary/50 bg-bg-interactive shadow-glow-sm"
                  : "border-transparent",
              )}
            >
              <span
                className={cn(
                  "flex size-8 shrink-0 items-center justify-center rounded-full text-body font-semibold tabular-nums",
                  isDone
                    ? "bg-status-success/15 text-status-success"
                    : isActive
                      ? "bg-brand-primary text-white"
                      : "bg-bg-interactive text-text-muted",
                )}
              >
                {isDone ? (
                  <Check aria-hidden="true" className="size-4" />
                ) : (
                  index + 1
                )}
              </span>
              <span className="min-w-0">
                <span
                  className={cn(
                    "block text-body font-semibold",
                    isActive || isDone
                      ? "text-text-primary"
                      : "text-text-secondary",
                  )}
                >
                  {step.label}
                </span>
                <span className="block truncate text-caption text-text-muted">
                  {step.sub}
                </span>
              </span>
            </li>
          );
        })}
      </ol>

      <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        {/* Capture panel */}
        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h3">Capture</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {status === "idle" || status === "selected" ? (
              <div className="rounded-lg border-2 border-dashed border-border-strong px-5 py-7 text-center">
                <span className="mx-auto flex size-14 items-center justify-center rounded-full bg-bg-interactive">
                  <UploadCloud
                    aria-hidden="true"
                    className="size-6 text-brand-primary"
                  />
                </span>
                <p className="mt-3 text-body-lg font-medium text-text-primary">
                  Choose a sample document
                </p>
                <p className="text-caption text-text-muted">
                  Drag &amp; drop and links arrive with the product. The demo
                  uses fixed samples so nothing uploads.
                </p>
                <div className="mx-auto mt-4 max-w-sm space-y-2">
                  {DEMO_DOCUMENTS.map((candidate) => {
                    const selected = state.intake.docId === candidate.id;

                    return (
                      <button
                        key={candidate.id}
                        type="button"
                        aria-pressed={selected}
                        onClick={() =>
                          dispatch({
                            type: "selectDocument",
                            docId: candidate.id,
                          })
                        }
                        className={cn(
                          "flex w-full items-center gap-2.5 rounded-md border px-3.5 py-2.5 text-left text-body transition-colors",
                          "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                          selected
                            ? "border-brand-primary/50 bg-bg-interactive text-text-primary"
                            : "border-border-default bg-bg-canvas text-text-secondary hover:border-brand-focus hover:text-text-primary",
                        )}
                      >
                        <FileText
                          aria-hidden="true"
                          className={cn(
                            "size-4 shrink-0",
                            selected ? "text-brand-primary" : "text-text-muted",
                          )}
                        />
                        <span className="min-w-0 flex-1">
                          <span className="block truncate">
                            {candidate.name}
                          </span>
                          <span className="block text-caption text-text-muted">
                            {candidate.kind}
                          </span>
                        </span>
                        {selected ? (
                          <CircleCheck
                            aria-hidden="true"
                            className="size-4 shrink-0 text-brand-primary"
                          />
                        ) : null}
                      </button>
                    );
                  })}
                </div>
                <p className="mt-4 flex items-center justify-center gap-1.5 text-caption text-text-muted">
                  <Lock aria-hidden="true" className="size-3" />
                  In the product: PDF, DOCX, text, and links with size limits,
                  private by default.
                </p>
              </div>
            ) : null}

            {doc && status !== "idle" && status !== "selected" ? (
              <div className="rounded-md border border-border-subtle bg-bg-subtle p-4 font-mono text-caption text-text-secondary">
                {doc.excerpt.map((line) => (
                  <p key={line}>{line}</p>
                ))}
              </div>
            ) : null}

            {status === "analyzing" ? (
              <ol aria-label="Analysis stages" className="space-y-2">
                {ANALYSIS_STAGES.map((stage, index) => (
                  <li
                    key={stage}
                    className={cn(
                      "flex items-center gap-2 text-body",
                      index < state.intake.stage && "text-text-secondary",
                      index === state.intake.stage && "text-text-primary",
                      index > state.intake.stage && "text-text-muted",
                    )}
                  >
                    {index < state.intake.stage ? (
                      <CircleCheck
                        aria-hidden="true"
                        className="size-4 text-status-success"
                      />
                    ) : index === state.intake.stage ? (
                      <Spinner size="sm" />
                    ) : (
                      <span
                        aria-hidden="true"
                        className="inline-block size-4 rounded-full border border-border-strong"
                      />
                    )}
                    {stage}
                  </li>
                ))}
              </ol>
            ) : null}

            {status === "ready" ? (
              <p className="flex items-center gap-2 text-body text-text-primary">
                <CircleCheck
                  aria-hidden="true"
                  className="size-4 text-status-success"
                />
                Extraction finished. Review the suggestions below.
              </p>
            ) : null}

            {status === "confirmed" ? (
              <p className="flex items-center gap-2 text-body text-text-primary">
                <CircleCheck
                  aria-hidden="true"
                  className="size-4 text-status-success"
                />
                Saved and organized. Retrying never duplicates records.
              </p>
            ) : null}

            <p aria-live="polite" className="sr-only">
              {status === "analyzing"
                ? `Analyzing: ${ANALYSIS_STAGES[state.intake.stage]}`
                : null}
              {status === "ready" ? "Suggestions are ready for review." : null}
            </p>
          </CardContent>
        </Card>

        {/* Preview panel */}
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center justify-between gap-2">
              <CardTitle as="h3">Preview</CardTitle>
              <Badge variant="ai">Suggestions only</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {status === "idle" || status === "selected" ? (
              <>
                {[
                  {
                    icon: Target,
                    tint: "bg-status-ai/12 text-status-ai",
                    title: "Suggested tasks",
                    sub: "Dated deadlines with the reason for each.",
                  },
                  {
                    icon: FolderOpen,
                    tint: "bg-status-info/12 text-status-info",
                    title: "Source saved to the right course",
                    sub: "The document lands in Resources, organized.",
                  },
                  {
                    icon: Brain,
                    tint: "bg-status-research/12 text-status-research",
                    title: "Key-term notes",
                    sub: "Second Brain entries linked to their source.",
                  },
                ].map((row) => (
                  <div
                    key={row.title}
                    className="flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3"
                  >
                    <span
                      className={cn(
                        "flex size-9 shrink-0 items-center justify-center rounded-md",
                        row.tint,
                      )}
                    >
                      <row.icon aria-hidden="true" className="size-4" />
                    </span>
                    <span>
                      <span className="block text-body font-medium text-text-primary">
                        {row.title}
                      </span>
                      <span className="block text-caption text-text-muted">
                        {row.sub}
                      </span>
                    </span>
                  </div>
                ))}
                <p className="text-caption text-text-muted">
                  Nothing is created automatically. You review everything at the
                  last step.
                </p>
              </>
            ) : null}

            {status === "analyzing" ? (
              <>
                {[0, 1, 2].map((row) => (
                  <div
                    key={row}
                    className="flex items-center gap-3 rounded-md border border-border-subtle px-3.5 py-3"
                  >
                    <Skeleton className="size-9 rounded-md" />
                    <div className="flex-1 space-y-1.5">
                      <Skeleton className="h-3.5 w-1/2" />
                      <Skeleton className="h-3 w-3/4" />
                    </div>
                  </div>
                ))}
              </>
            ) : null}

            {(status === "ready" || status === "confirmed") && doc ? (
              <>
                <div className="flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3">
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-deadline/12 text-status-deadline">
                    <CalendarClock aria-hidden="true" className="size-4" />
                  </span>
                  <span className="text-body text-text-primary">
                    <span className="font-semibold tabular-nums">
                      {taskCount}
                    </span>{" "}
                    dated {taskCount === 1 ? "task" : "tasks"} found
                  </span>
                </div>
                <div className="flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3">
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-info/12 text-status-info">
                    <FolderOpen aria-hidden="true" className="size-4" />
                  </span>
                  <span className="text-body text-text-primary">
                    1 source ready to file under its course
                  </span>
                </div>
                {noteCount > 0 ? (
                  <div className="flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-research/12 text-status-research">
                      <Brain aria-hidden="true" className="size-4" />
                    </span>
                    <span className="text-body text-text-primary">
                      {noteCount} key-term note suggestion
                    </span>
                  </div>
                ) : null}
                <p className="text-caption text-text-muted">
                  Each suggestion carries its reason. Accept or reject them
                  individually below.
                </p>
              </>
            ) : null}
          </CardContent>
        </Card>
      </div>

      {status === "selected" ? (
        <div className="flex flex-col items-center gap-2">
          <Button
            size="lg"
            glow
            onClick={() => dispatch({ type: "startAnalysis" })}
          >
            <Sparkles aria-hidden="true" className="size-4" />
            Analyze &amp; organize
          </Button>
          <p className="text-caption text-text-muted">
            Simulated analysis: a fixed sequence in your browser, not a real
            model call.
          </p>
        </div>
      ) : null}

      {status === "ready" && doc ? (
        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h3">Review the suggestions</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <ul className="space-y-3">
              {doc.suggestions.map((suggestion) => {
                const checkboxId = `intake-${suggestion.id}`;

                return (
                  <li
                    key={suggestion.id}
                    className="flex items-start gap-3 rounded-lg border border-border-default bg-bg-canvas p-4"
                  >
                    <input
                      id={checkboxId}
                      type="checkbox"
                      checked={state.intake.accepted.includes(suggestion.id)}
                      onChange={() =>
                        dispatch({
                          type: "toggleSuggestion",
                          id: suggestion.id,
                        })
                      }
                      className="mt-1 size-4.5 accent-brand-primary"
                    />
                    <label htmlFor={checkboxId} className="flex-1 space-y-1">
                      <span className="flex flex-wrap items-center gap-2">
                        <span className="text-body font-medium text-text-primary">
                          {suggestion.label}
                        </span>
                        <Badge
                          variant={
                            suggestion.kind === "task"
                              ? "deadline"
                              : suggestion.kind === "resource"
                                ? "info"
                                : "research"
                          }
                        >
                          {suggestion.kind === "task"
                            ? "Planner task"
                            : suggestion.kind === "resource"
                              ? "Resource"
                              : "Second Brain"}
                        </Badge>
                      </span>
                      <span className="block text-body text-text-secondary">
                        {suggestion.detail}
                      </span>
                      <span className="block text-caption text-text-muted">
                        Reason: {suggestion.reason}
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
            <Button
              glow
              disabled={acceptedCount === 0}
              onClick={() => dispatch({ type: "confirmIntake" })}
            >
              Confirm {acceptedCount}{" "}
              {acceptedCount === 1 ? "suggestion" : "suggestions"}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {status === "confirmed" && doc ? (
        <div className="space-y-3">
          <Alert variant="success" title="Saved to your demo workspace">
            You confirmed {acceptedCount} of {doc.suggestions.length}{" "}
            suggestions: tasks are on the planner, the document is a course
            resource, and notes landed in the Second Brain.
          </Alert>
          <div className="flex flex-wrap gap-3">
            <Button
              onClick={() => dispatch({ type: "navigate", view: "dashboard" })}
            >
              See it on the dashboard
              <ArrowRight aria-hidden="true" className="size-4" />
            </Button>
            <Button
              variant="secondary"
              onClick={() => dispatch({ type: "navigate", view: "planner" })}
            >
              Open the planner
            </Button>
            <Button
              variant="ghost"
              onClick={() => dispatch({ type: "captureAnother" })}
            >
              Capture another sample
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
