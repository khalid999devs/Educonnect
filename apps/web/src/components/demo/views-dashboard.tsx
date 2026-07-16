"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  Spinner,
} from "@educonnect/ui";
import {
  ArrowRight,
  BookOpen,
  Bookmark,
  Brain,
  CalendarClock,
  ChevronRight,
  CircleCheck,
  Clock,
  FileText,
  Inbox,
  ListChecks,
  PenLine,
  Quote,
  Star,
  Target,
  TrendingUp,
  UploadCloud,
  type LucideIcon,
} from "lucide-react";

import Image from "next/image";

import { WeeklyChart } from "./weekly-chart";

import { demoProgress, useDemo } from "./demo-app";
import {
  ANALYSIS_STAGES,
  DEMO_COURSES,
  DEMO_DOCUMENTS,
  DEMO_PERSONA,
  FOCUS_SESSION,
  NEXT_CLASS,
  RECOMMENDED_TOOLS,
} from "./demo-data";

const TOOL_VISUALS: Record<string, { icon: LucideIcon; classes: string }> = {
  writing: { icon: PenLine, classes: "bg-status-info/12 text-status-info" },
  citation: { icon: Quote, classes: "bg-status-ai/12 text-status-ai" },
  workflow: {
    icon: ListChecks,
    classes: "bg-status-success/12 text-status-success",
  },
};

const NOTE_TINTS = [
  "bg-status-info/12 text-status-info",
  "bg-status-research/12 text-status-research",
  "bg-status-ai/12 text-status-ai",
];

export function DashboardView() {
  const { state, dispatch } = useDemo();
  const progress = demoProgress(state);
  const activeDoc =
    DEMO_DOCUMENTS.find((doc) => doc.id === state.intake.docId) ?? null;
  const openTasks = [...state.tasks]
    .filter((task) => !task.completed)
    .sort((a, b) => a.dayIndex - b.dayIndex);
  const nearestDeadline = openTasks[0];
  const sortedTools = [...RECOMMENDED_TOOLS].sort(
    (a, b) =>
      Number(state.savedToolIds.includes(b.id)) -
      Number(state.savedToolIds.includes(a.id)),
  );

  return (
    <div className="space-y-4">
      {/* 1 — greeting / academic cover (Notion-style photo band) */}
      <div className="relative min-h-52 overflow-hidden rounded-xl border border-border-default lg:min-h-60">
        <Image
          src="/marketing/study-desk.jpg"
          alt=""
          fill
          priority
          sizes="(max-width: 1024px) 100vw, 1100px"
          className="object-cover object-center"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-r from-bg-canvas/95 via-bg-canvas/75 to-bg-canvas/20"
        />
        <div
          aria-hidden="true"
          className="absolute inset-x-0 bottom-0 h-16 bg-linear-to-t from-bg-canvas/70 to-transparent"
        />
        <div className="relative max-w-lg space-y-3 p-6 lg:p-8">
          <h2 className="text-h2 text-text-primary">
            Good evening, {DEMO_PERSONA.name} 👋
          </h2>
          <p className="text-body-lg text-text-secondary">
            Keep going — small steps today, big impact tomorrow.
          </p>
          <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-primary/40 bg-bg-canvas/70 px-3 py-1 text-caption font-medium text-brand-primary backdrop-blur-sm">
            <Star aria-hidden="true" className="size-3.5" />
            You got this
          </span>
          <p className="text-caption text-text-secondary">
            {DEMO_PERSONA.program} · {DEMO_PERSONA.term} · {DEMO_COURSES.length}{" "}
            active courses
          </p>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-3">
        {/* 2 — the single Quick Intake module */}
        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h3" className="flex items-center gap-2.5">
              <span className="flex size-8 items-center justify-center rounded-md bg-brand-primary/12">
                <Inbox
                  aria-hidden="true"
                  className="size-4 text-brand-primary"
                />
              </span>
              Quick Intake
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {state.intake.status === "idle" ? (
              <div className="rounded-lg border-2 border-dashed border-border-strong px-4 py-5 text-center">
                <span className="mx-auto flex size-11 items-center justify-center rounded-full bg-bg-interactive">
                  <UploadCloud
                    aria-hidden="true"
                    className="size-5 text-brand-primary"
                  />
                </span>
                <p className="mt-2 text-body font-medium text-text-primary">
                  Capture a sample document
                </p>
                <p className="text-caption text-text-muted">
                  Analysis is simulated — nothing uploads.
                </p>
                <div className="mt-3 space-y-2">
                  {DEMO_DOCUMENTS.map((doc) => (
                    <button
                      key={doc.id}
                      type="button"
                      onClick={() =>
                        dispatch({ type: "chooseDocument", docId: doc.id })
                      }
                      className="flex w-full items-center gap-2 rounded-md border border-border-default bg-bg-canvas px-3 py-2 text-left text-caption text-text-primary transition-colors hover:border-brand-focus hover:bg-bg-interactive focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
                    >
                      <FileText
                        aria-hidden="true"
                        className="size-4 shrink-0 text-text-muted"
                      />
                      <span className="truncate">{doc.name}</span>
                    </button>
                  ))}
                </div>
              </div>
            ) : null}

            {state.intake.status === "selected" ? (
              <>
                <p className="text-body text-text-primary">
                  Sample selected — run the analysis from Smart Intake.
                </p>
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => dispatch({ type: "navigate", view: "intake" })}
                >
                  Open Smart Intake
                  <ArrowRight aria-hidden="true" className="size-4" />
                </Button>
              </>
            ) : null}

            {state.intake.status === "analyzing" ? (
              <ul className="space-y-2">
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
              </ul>
            ) : null}

            {state.intake.status === "ready" ? (
              <p className="flex items-center gap-2 text-body text-text-primary">
                <CircleCheck
                  aria-hidden="true"
                  className="size-4 text-status-success"
                />
                Suggestions ready — review them in What's Next.
              </p>
            ) : null}

            {state.intake.status === "confirmed" ? (
              <>
                <p className="flex items-center gap-2 text-body text-text-primary">
                  <CircleCheck
                    aria-hidden="true"
                    className="size-4 text-status-success"
                  />
                  Capture saved and organized.
                </p>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => dispatch({ type: "captureAnother" })}
                >
                  Capture another sample
                </Button>
              </>
            ) : null}

            <p className="text-caption text-text-muted">
              In the product: PDF, DOCX, text, and links · sample files only
              here.
            </p>
          </CardContent>
        </Card>

        {/* 3 — What's Next */}
        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h3" className="flex items-center gap-2.5">
              <span className="flex size-8 items-center justify-center rounded-md bg-brand-primary/12">
                <Target
                  aria-hidden="true"
                  className="size-4 text-brand-primary"
                />
              </span>
              What's Next
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {state.intake.status === "ready" && activeDoc ? (
              <div className="rounded-lg border border-brand-primary/40 bg-bg-interactive p-4">
                <p className="flex items-start gap-2.5">
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-bg-surface">
                    <FileText
                      aria-hidden="true"
                      className="size-4 text-brand-primary"
                    />
                  </span>
                  <span>
                    <span className="block text-body font-semibold text-text-primary">
                      Review extracted suggestions
                    </span>
                    <span className="block text-caption text-text-secondary">
                      We drafted {activeDoc.suggestions.length} suggestions from{" "}
                      {activeDoc.name} — each with its reason.
                    </span>
                  </span>
                </p>
                <Button
                  glow
                  fullWidth
                  size="sm"
                  className="mt-3"
                  onClick={() => dispatch({ type: "navigate", view: "intake" })}
                >
                  Review now
                  <ArrowRight aria-hidden="true" className="size-4" />
                </Button>
              </div>
            ) : null}
            {openTasks.slice(0, 3).map((task) => (
              <label
                key={task.id}
                className="flex cursor-pointer items-start gap-2.5 rounded-md border border-border-subtle px-3 py-2.5 transition-colors hover:border-border-strong"
              >
                <input
                  type="checkbox"
                  checked={false}
                  onChange={() => dispatch({ type: "toggleTask", id: task.id })}
                  className="mt-0.5 size-4 accent-brand-primary"
                  aria-label={`Mark "${task.title}" as done`}
                />
                <span>
                  <span className="block text-body text-text-primary">
                    {task.title}
                  </span>
                  <span className="block text-caption tabular-nums text-text-muted">
                    {task.courseCode} · due {task.due}
                  </span>
                </span>
              </label>
            ))}
            {openTasks.length === 0 && state.intake.status !== "ready" ? (
              <p className="text-body text-text-secondary">
                Nothing open. That's real — no filler tasks here.
              </p>
            ) : null}
          </CardContent>
        </Card>

        {/* 4 — recommended tools, saved-first */}
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center justify-between gap-2">
              <CardTitle as="h3">Recommended study tools</CardTitle>
              <button
                type="button"
                onClick={() => dispatch({ type: "navigate", view: "tools" })}
                className="text-caption font-medium text-brand-primary hover:underline"
              >
                View all
              </button>
            </div>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {sortedTools.map((tool) => {
              const saved = state.savedToolIds.includes(tool.id);
              const visual = TOOL_VISUALS[tool.id] ?? {
                icon: ListChecks,
                classes: "bg-bg-interactive text-brand-primary",
              };

              return (
                <div
                  key={tool.id}
                  className="flex items-center gap-3 rounded-md border border-border-subtle px-3 py-2.5 transition-colors hover:border-border-strong"
                >
                  <span
                    className={cn(
                      "flex size-10 shrink-0 items-center justify-center rounded-md",
                      visual.classes,
                    )}
                  >
                    <visual.icon aria-hidden="true" className="size-5" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-1.5 text-body font-medium text-text-primary">
                      {tool.name}
                      <Badge variant="neutral">Sample</Badge>
                    </p>
                    <p className="truncate text-caption text-text-muted">
                      {tool.blurb}
                    </p>
                  </div>
                  <button
                    type="button"
                    aria-pressed={saved}
                    aria-label={
                      saved ? `Unsave ${tool.name}` : `Save ${tool.name}`
                    }
                    onClick={() =>
                      dispatch({ type: "toggleTool", id: tool.id })
                    }
                    className={cn(
                      "rounded-md p-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                      saved
                        ? "text-brand-primary"
                        : "text-text-muted hover:text-text-primary",
                    )}
                  >
                    <Bookmark
                      aria-hidden="true"
                      className={cn("size-4", saved && "fill-current")}
                    />
                  </button>
                  <button
                    type="button"
                    aria-label={`Open guidance for ${tool.name}`}
                    onClick={() =>
                      dispatch({ type: "navigate", view: "tools" })
                    }
                    className="rounded-md p-1 text-text-muted hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
                  >
                    <ChevronRight aria-hidden="true" className="size-4" />
                  </button>
                </div>
              );
            })}
          </CardContent>
        </Card>
      </div>

      {/* 5 — today: next class, deadline, focus session */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card className="flex items-start justify-between gap-3">
          <div>
            <p className="text-caption text-text-muted">Next class</p>
            <p className="mt-1 text-h4 text-text-primary">
              {NEXT_CLASS.course.split(" ").slice(1).join(" ")}
            </p>
            <p className="mt-1 text-caption tabular-nums text-text-secondary">
              {NEXT_CLASS.time}
            </p>
            <p className="text-caption text-text-muted">{NEXT_CLASS.room}</p>
          </div>
          <span className="flex size-13 shrink-0 items-center justify-center rounded-xl bg-status-info/12">
            <BookOpen aria-hidden="true" className="size-6 text-status-info" />
          </span>
        </Card>

        <Card className="flex items-start justify-between gap-3">
          <div>
            <p className="text-caption text-text-muted">Upcoming deadline</p>
            {nearestDeadline ? (
              <>
                <p className="mt-1 text-h4 text-text-primary">
                  {nearestDeadline.title}
                </p>
                {nearestDeadline.dueNote ? (
                  <p className="mt-1 text-caption font-semibold text-status-deadline">
                    {nearestDeadline.dueNote}
                  </p>
                ) : null}
                <p className="text-caption tabular-nums text-text-muted">
                  Due {nearestDeadline.due} · {nearestDeadline.courseCode}
                </p>
              </>
            ) : (
              <p className="mt-1 text-body text-text-secondary">
                No open deadlines.
              </p>
            )}
          </div>
          <span className="flex size-13 shrink-0 items-center justify-center rounded-xl bg-status-deadline/12">
            <CalendarClock
              aria-hidden="true"
              className="size-6 text-status-deadline"
            />
          </span>
        </Card>

        <Card className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-caption text-text-muted">Focus session</p>
            <p className="mt-1 text-h4 text-text-primary">
              {FOCUS_SESSION.label}
            </p>
            <p className="text-caption text-text-secondary">
              {FOCUS_SESSION.minutes} min · {FOCUS_SESSION.topic}
            </p>
            {state.focus === "ready" ? (
              <Button
                variant="secondary"
                size="sm"
                className="mt-2"
                onClick={() => dispatch({ type: "startFocus" })}
              >
                Start session
              </Button>
            ) : state.focus === "running" ? (
              <Button
                size="sm"
                className="mt-2"
                onClick={() => dispatch({ type: "completeFocus" })}
              >
                Complete session
              </Button>
            ) : (
              <p className="mt-2 text-caption font-medium text-status-success">
                Completed — {FOCUS_SESSION.minutes} real minutes counted.
              </p>
            )}
          </div>
          <span className="flex size-13 shrink-0 items-center justify-center rounded-xl bg-status-success/12">
            <Target aria-hidden="true" className="size-6 text-status-success" />
          </span>
        </Card>
      </div>

      <div className="grid gap-4 xl:grid-cols-2">
        {/* 6 — Second Brain recents */}
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center justify-between gap-2">
              <CardTitle as="h3" className="flex items-center gap-2.5">
                <span className="flex size-8 items-center justify-center rounded-md bg-status-research/12">
                  <Brain
                    aria-hidden="true"
                    className="size-4 text-status-research"
                  />
                </span>
                Research &amp; academic Second Brain
              </CardTitle>
              <button
                type="button"
                onClick={() => dispatch({ type: "navigate", view: "brain" })}
                className="text-caption font-medium text-brand-primary hover:underline"
              >
                View all
              </button>
            </div>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {state.notes.slice(0, 3).map((note, index) => (
              <div
                key={note.id}
                className="flex items-center gap-3 rounded-md border border-border-subtle px-3 py-2.5 transition-colors hover:border-border-strong"
              >
                <span
                  className={cn(
                    "flex size-9 shrink-0 items-center justify-center rounded-md",
                    NOTE_TINTS[index % NOTE_TINTS.length],
                  )}
                >
                  <FileText aria-hidden="true" className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-body font-medium text-text-primary">
                    {note.title}
                  </p>
                  <p className="truncate text-caption text-text-muted">
                    {note.meta}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <Badge variant="research">{note.courseCode}</Badge>
                  <span className="text-caption tabular-nums text-text-muted">
                    {note.date}
                  </span>
                </div>
              </div>
            ))}
            <Button
              fullWidth
              size="sm"
              onClick={() => dispatch({ type: "navigate", view: "brain" })}
            >
              Open my Second Brain
              <ArrowRight aria-hidden="true" className="size-4" />
            </Button>
          </CardContent>
        </Card>

        {/* 7 — one truthful progress region */}
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center justify-between gap-2">
              <CardTitle as="h3" className="flex items-center gap-2.5">
                <span className="flex size-8 items-center justify-center rounded-md bg-brand-primary/12">
                  <TrendingUp
                    aria-hidden="true"
                    className="size-4 text-brand-primary"
                  />
                </span>
                My progress
              </CardTitle>
              <Badge variant="neutral">This week</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-center gap-5">
              <div
                role="img"
                aria-label={`${progress.percent} percent of this week's tasks completed`}
                className="relative flex size-28 shrink-0 items-center justify-center rounded-full"
                style={{
                  background: `conic-gradient(var(--brand-primary) ${progress.percent * 3.6}deg, var(--bg-interactive) 0deg)`,
                }}
              >
                <span className="flex size-22 flex-col items-center justify-center rounded-full bg-bg-surface text-center">
                  <span className="text-h3 tabular-nums text-text-primary">
                    {progress.percent}%
                  </span>
                  <span className="px-2 text-caption leading-tight text-text-muted">
                    weekly tasks
                  </span>
                </span>
              </div>
              <div className="min-w-0 flex-1">
                <WeeklyChart weekly={progress.weekly} />
              </div>
            </div>

            <p className="text-body text-text-primary">
              You completed{" "}
              <span className="font-semibold tabular-nums">
                {progress.completed} of {progress.total}
              </span>{" "}
              tasks this week
              {progress.focusMinutes > 0 ? (
                <>
                  {" "}
                  and logged{" "}
                  <span className="font-semibold tabular-nums">
                    {progress.focusMinutes}
                  </span>{" "}
                  focus minutes
                </>
              ) : null}
              .{" "}
              {progress.nextTask
                ? `Next action: ${progress.nextTask.title} (${progress.nextTask.courseCode}).`
                : "Next action: review your captured suggestions."}
            </p>

            <div className="grid grid-cols-3 gap-2.5">
              <div className="rounded-md border border-border-subtle p-3 text-center">
                <CircleCheck
                  aria-hidden="true"
                  className="mx-auto size-4 text-status-success"
                />
                <p className="mt-1 text-h4 tabular-nums text-text-primary">
                  {progress.completed}/{progress.total}
                </p>
                <p className="text-caption text-text-muted">Tasks done</p>
              </div>
              <div className="rounded-md border border-border-subtle p-3 text-center">
                <Clock
                  aria-hidden="true"
                  className="mx-auto size-4 text-brand-primary"
                />
                <p className="mt-1 text-h4 tabular-nums text-text-primary">
                  {progress.focusMinutes}m
                </p>
                <p className="text-caption text-text-muted">Focus time</p>
              </div>
              <div className="rounded-md border border-border-subtle p-3 text-center">
                <CalendarClock
                  aria-hidden="true"
                  className="mx-auto size-4 text-status-deadline"
                />
                <p className="mt-1 text-h4 tabular-nums text-text-primary">
                  {progress.total - progress.completed}
                </p>
                <p className="text-caption text-text-muted">Still open</p>
              </div>
            </div>

            <p className="text-caption text-text-muted">
              Every number here is computed from what you did in this demo — no
              streaks, no invented percentages.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
