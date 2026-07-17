"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  Input,
  Spinner,
} from "@educonnect/ui";
import {
  Bookmark,
  Brain,
  CalendarClock,
  CircleCheck,
  FileText,
  Inbox,
  Link2,
  Sparkles,
  Star,
  Target,
  TrendingUp,
  Wrench,
} from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useState, type FormEvent } from "react";

import type { Dashboard, DashboardTask } from "@/lib/api/dashboard";
import { dueNote, formatDueAt, formatTime, weekdayLetter } from "./format";

export function CoverSection({
  dashboard,
  greeting,
}: {
  dashboard: Dashboard;
  greeting: string;
}) {
  const { cover } = dashboard;
  const program = [cover.degree, cover.major].filter(Boolean).join(" · ");
  const metaLine = [
    cover.institution,
    program === "" ? null : program,
    cover.study_stage,
  ]
    .filter(Boolean)
    .join(" · ");

  return (
    <div className="relative min-h-52 overflow-hidden rounded-xl border border-border-default lg:min-h-56">
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
      <div className="relative max-w-xl space-y-3 p-6 lg:p-8">
        <h1 className="text-h2 text-text-primary">
          {greeting}, {cover.name.split(" ")[0]} 👋
        </h1>
        {metaLine !== "" ? (
          <p className="text-body-lg text-text-secondary">{metaLine}</p>
        ) : (
          <p className="text-body-lg text-text-secondary">
            <Link
              href="/onboarding"
              className="text-brand-primary hover:underline"
            >
              Finish setup
            </Link>{" "}
            to personalize your cover.
          </p>
        )}
        <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-primary/40 bg-bg-canvas/70 px-3 py-1 text-caption font-medium text-brand-primary backdrop-blur-sm">
          <Star aria-hidden="true" className="size-3.5" />
          You got this
        </span>
        <p className="text-caption tabular-nums text-text-secondary">
          {cover.term ? `${cover.term.label} · ` : ""}
          {cover.active_course_count} active{" "}
          {cover.active_course_count === 1 ? "course" : "courses"}
        </p>
      </div>
    </div>
  );
}

const INTAKE_STATE_LABELS: Record<string, string> = {
  awaiting_review: "Awaiting your review",
  failed_retryable: "Failed — can be retried",
  failed_final: "Failed",
};

function intakeStateLabel(state: string): string {
  return (
    INTAKE_STATE_LABELS[state] ??
    state.replaceAll("_", " ").replace(/^./, (c) => c.toUpperCase())
  );
}

export function QuickIntakeSection({
  dashboard,
  busy,
  onCaptureLink,
}: {
  dashboard: Dashboard;
  busy: boolean;
  onCaptureLink: (url: string) => Promise<boolean>;
}) {
  const [url, setUrl] = useState("");
  const [localError, setLocalError] = useState<string | null>(null);
  const { quick_intake: quickIntake } = dashboard;

  const submit = async (event: FormEvent) => {
    event.preventDefault();

    if (!url.startsWith("https://")) {
      setLocalError("Links must start with https://");

      return;
    }

    setLocalError(null);

    if (await onCaptureLink(url)) {
      setUrl("");
    }
  };

  return (
    <Card>
      <CardHeader className="mb-3">
        <CardTitle as="h2" className="flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-md bg-brand-primary/12">
            <Inbox aria-hidden="true" className="size-4 text-brand-primary" />
          </span>
          Quick Intake
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <form onSubmit={submit} className="space-y-2" noValidate>
          <label
            htmlFor="quick-intake-url"
            className="block text-label text-text-secondary"
          >
            Paste an academic link
          </label>
          <div className="flex gap-2">
            <div className="relative min-w-0 flex-1">
              <Link2
                aria-hidden="true"
                className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
              />
              <Input
                id="quick-intake-url"
                type="url"
                placeholder="https://…"
                value={url}
                onChange={(event) => setUrl(event.target.value)}
                aria-invalid={localError ? true : undefined}
                className="pl-9"
              />
            </div>
            <Button type="submit" isLoading={busy}>
              Capture
            </Button>
          </div>
          {localError ? (
            <p className="text-caption text-status-error">{localError}</p>
          ) : (
            <p className="text-caption text-text-muted">
              https links only · processed in the background · you review every
              suggestion. File uploads arrive with the Smart Intake page.
            </p>
          )}
        </form>

        {quickIntake.active_item ? (
          <div className="flex items-start gap-2.5 rounded-md border border-border-subtle px-3 py-2.5">
            <FileText
              aria-hidden="true"
              className="mt-0.5 size-4 shrink-0 text-text-muted"
            />
            <p className="text-body text-text-secondary">
              Latest capture:{" "}
              <span className="font-medium text-text-primary">
                {intakeStateLabel(quickIntake.active_item.state)}
              </span>
              {quickIntake.active_item.failure_code
                ? ` (${quickIntake.active_item.failure_code.replaceAll("_", " ")})`
                : ""}
            </p>
          </div>
        ) : null}

        {quickIntake.awaiting_review_count > 0 ? (
          <p className="flex items-center gap-2 text-body text-text-primary">
            <Badge variant="deadline">
              {quickIntake.awaiting_review_count} awaiting review
            </Badge>
            <span className="text-caption text-text-muted">
              The review screen ships with the Smart Intake page.
            </span>
          </p>
        ) : null}
      </CardContent>
    </Card>
  );
}

export function WhatsNextSection({
  dashboard,
  busyTaskId,
  onCompleteTask,
}: {
  dashboard: Dashboard;
  busyTaskId: string | null;
  onCompleteTask: (task: DashboardTask) => void;
}) {
  const { whats_next: whatsNext } = dashboard;

  return (
    <Card>
      <CardHeader className="mb-3">
        <div className="flex items-center justify-between gap-2">
          <CardTitle as="h2" className="flex items-center gap-2.5">
            <span className="flex size-8 items-center justify-center rounded-md bg-brand-primary/12">
              <Target
                aria-hidden="true"
                className="size-4 text-brand-primary"
              />
            </span>
            What's Next
          </CardTitle>
          <p className="text-caption tabular-nums text-text-muted">
            {whatsNext.overdue_count} overdue · {whatsNext.upcoming_count}{" "}
            upcoming
          </p>
        </div>
      </CardHeader>
      <CardContent className="space-y-2.5">
        {whatsNext.tasks.length === 0 ? (
          <p className="text-body text-text-secondary">
            No open dated tasks. That's real — add work from the planner when it
            ships, or capture a syllabus above.
          </p>
        ) : (
          whatsNext.tasks.map((task) => {
            const note = dueNote(task.due_at, dashboard);

            return (
              <div
                key={task.id}
                className={cn(
                  "flex items-start gap-2.5 rounded-md border px-3 py-2.5 transition-colors",
                  task.overdue
                    ? "border-status-error/40 bg-status-error/5"
                    : "border-border-subtle hover:border-border-strong",
                )}
              >
                {busyTaskId === task.id ? (
                  <span className="mt-0.5">
                    <Spinner size="sm" label="Completing task" />
                  </span>
                ) : (
                  <input
                    type="checkbox"
                    checked={false}
                    disabled={busyTaskId !== null}
                    onChange={() => onCompleteTask(task)}
                    className="mt-0.5 size-4 accent-brand-primary"
                    aria-label={`Mark "${task.title}" as done`}
                  />
                )}
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-body text-text-primary">
                    {task.title}
                  </span>
                  <span className="block text-caption tabular-nums text-text-muted">
                    {task.course ? `${task.course.title} · ` : ""}
                    {formatDueAt(task.due_at, dashboard.timeframe.timezone)}
                  </span>
                </span>
                {note ? (
                  <span
                    className={cn(
                      "shrink-0 text-caption font-semibold tabular-nums",
                      task.overdue
                        ? "text-status-error"
                        : "text-status-deadline",
                    )}
                  >
                    {note}
                  </span>
                ) : null}
              </div>
            );
          })
        )}
      </CardContent>
    </Card>
  );
}

export function ToolsSection({
  dashboard,
  busyToolId,
  onToggleSave,
}: {
  dashboard: Dashboard;
  busyToolId: string | null;
  onToggleSave: (toolId: string, saved: boolean) => void;
}) {
  return (
    <Card>
      <CardHeader className="mb-3">
        <CardTitle as="h2" className="flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-md bg-status-ai/12">
            <Sparkles aria-hidden="true" className="size-4 text-status-ai" />
          </span>
          Recommended study tools
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-2.5">
        {dashboard.tools.length === 0 ? (
          <p className="text-body text-text-secondary">
            No reviewed tools are published yet — the catalog fills through
            human curation, never fabricated entries.
          </p>
        ) : (
          dashboard.tools.map((tool) => (
            <div
              key={tool.id}
              className="flex items-center gap-3 rounded-md border border-border-subtle px-3 py-2.5 transition-colors hover:border-border-strong"
            >
              <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-info/12 text-status-info">
                <Wrench aria-hidden="true" className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-body font-medium text-text-primary">
                  {tool.name}
                </p>
                {tool.category ? (
                  <p className="text-caption text-text-muted">
                    {tool.category}
                  </p>
                ) : null}
              </div>
              {busyToolId === tool.id ? (
                <Spinner size="sm" label="Updating tool preference" />
              ) : (
                <button
                  type="button"
                  aria-pressed={tool.saved}
                  aria-label={
                    tool.saved ? `Unsave ${tool.name}` : `Save ${tool.name}`
                  }
                  disabled={busyToolId !== null}
                  onClick={() => onToggleSave(tool.id, tool.saved)}
                  className={cn(
                    "rounded-md p-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                    tool.saved
                      ? "text-brand-primary"
                      : "text-text-muted hover:text-text-primary",
                  )}
                >
                  <Bookmark
                    aria-hidden="true"
                    className={cn("size-4", tool.saved && "fill-current")}
                  />
                </button>
              )}
            </div>
          ))
        )}
      </CardContent>
    </Card>
  );
}

export function TodaySection({ dashboard }: { dashboard: Dashboard }) {
  const { today } = dashboard;
  const session = today.next_focus_session;

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card>
        <CardHeader className="mb-3">
          <div className="flex items-center justify-between gap-2">
            <CardTitle as="h2" className="flex items-center gap-2.5">
              <span className="flex size-8 items-center justify-center rounded-md bg-status-deadline/12">
                <CalendarClock
                  aria-hidden="true"
                  className="size-4 text-status-deadline"
                />
              </span>
              Due today
            </CardTitle>
            <p className="text-caption tabular-nums text-text-muted">
              {today.due_task_count} total
            </p>
          </div>
        </CardHeader>
        <CardContent className="space-y-2.5">
          {today.due_tasks.length === 0 ? (
            <p className="text-body text-text-secondary">
              Nothing due today — honestly.
            </p>
          ) : (
            today.due_tasks.map((task) => (
              <p
                key={task.id}
                className="flex items-center justify-between gap-3 rounded-md border border-border-subtle px-3 py-2.5"
              >
                <span className="truncate text-body text-text-primary">
                  {task.title}
                </span>
                <span className="shrink-0 text-caption tabular-nums text-text-muted">
                  {formatTime(task.due_at, dashboard.timeframe.timezone)}
                </span>
              </p>
            ))
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="mb-3">
          <CardTitle as="h2" className="flex items-center gap-2.5">
            <span className="flex size-8 items-center justify-center rounded-md bg-status-success/12">
              <Target
                aria-hidden="true"
                className="size-4 text-status-success"
              />
            </span>
            Focus session
          </CardTitle>
        </CardHeader>
        <CardContent>
          {session === null ? (
            <p className="text-body text-text-secondary">
              No focus session planned. Scheduling lives in the planner page
              when it ships.
            </p>
          ) : (
            <div className="space-y-1.5">
              <p className="flex flex-wrap items-center gap-2 text-body font-medium text-text-primary">
                {session.task_title ?? "Focus session"}
                {session.in_progress ? (
                  <Badge variant="success">In progress</Badge>
                ) : null}
              </p>
              <p className="text-caption tabular-nums text-text-secondary">
                {formatTime(session.starts_at, dashboard.timeframe.timezone)} –{" "}
                {formatTime(session.ends_at, dashboard.timeframe.timezone)}
                {session.course_title ? ` · ${session.course_title}` : ""}
              </p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

const SOURCE_TYPE_LABEL: Record<string, string> = {
  resource: "Resource",
  link: "Link",
  none: "Note",
};

export function SecondBrainSection({ dashboard }: { dashboard: Dashboard }) {
  const { second_brain: secondBrain } = dashboard;

  return (
    <Card>
      <CardHeader className="mb-3">
        <div className="flex items-center justify-between gap-2">
          <CardTitle as="h2" className="flex items-center gap-2.5">
            <span className="flex size-8 items-center justify-center rounded-md bg-status-research/12">
              <Brain
                aria-hidden="true"
                className="size-4 text-status-research"
              />
            </span>
            Second Brain
          </CardTitle>
          <p className="text-caption tabular-nums text-text-muted">
            {secondBrain.total_item_count}{" "}
            {secondBrain.total_item_count === 1 ? "item" : "items"}
          </p>
        </div>
      </CardHeader>
      <CardContent className="space-y-2.5">
        {secondBrain.recent_items.length === 0 ? (
          <p className="text-body text-text-secondary">
            No knowledge items yet. Confirmed intake captures and the Second
            Brain page (coming next) fill this space with your real notes.
          </p>
        ) : (
          secondBrain.recent_items.map((item) => (
            <div
              key={item.id}
              className="flex items-center gap-3 rounded-md border border-border-subtle px-3 py-2.5"
            >
              <FileText
                aria-hidden="true"
                className="size-4 shrink-0 text-status-research"
              />
              <p className="min-w-0 flex-1 truncate text-body text-text-primary">
                {item.title}
              </p>
              <Badge variant="research">
                {SOURCE_TYPE_LABEL[item.source_type] ?? item.source_type}
              </Badge>
            </div>
          ))
        )}
      </CardContent>
    </Card>
  );
}

function ProgressChart({ dashboard }: { dashboard: Dashboard }) {
  const daily = dashboard.progress.daily_completed;

  if (daily.length === 0) {
    return null;
  }

  const max = Math.max(1, ...daily.map((day) => day.completed));
  const step = daily.length > 1 ? 196 / (daily.length - 1) : 0;
  const points = daily.map(
    (day, index) =>
      [12 + index * step, 46 - (day.completed / max) * 32] as const,
  );
  const line = points.map((point) => point.join(",")).join(" ");
  const summary = daily
    .map((day) => `${weekdayLetter(day.date)} ${day.completed}`)
    .join(", ");

  return (
    <svg
      viewBox="0 0 220 62"
      role="img"
      aria-label={`Tasks completed per day this week: ${summary}`}
      className="w-full"
    >
      <defs>
        <linearGradient
          id="dashboard-progress-fill"
          x1="0"
          y1="0"
          x2="0"
          y2="1"
        >
          <stop
            offset="0"
            stopColor="var(--brand-primary)"
            stopOpacity="0.32"
          />
          <stop offset="1" stopColor="var(--brand-primary)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon
        points={`12,50 ${line} ${points[points.length - 1]![0]},50`}
        fill="url(#dashboard-progress-fill)"
      />
      <polyline
        points={line}
        fill="none"
        stroke="var(--brand-primary)"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      {points.map((point, index) => (
        <circle
          key={daily[index]!.date}
          cx={point[0]}
          cy={point[1]}
          r="3"
          fill="var(--brand-focus)"
        />
      ))}
      {points.map((point, index) => (
        <text
          key={`label-${daily[index]!.date}`}
          x={point[0]}
          y={59}
          textAnchor="middle"
          fontSize="7.5"
          fill="var(--text-muted)"
        >
          {weekdayLetter(daily[index]!.date)}
        </text>
      ))}
    </svg>
  );
}

export function ProgressSection({ dashboard }: { dashboard: Dashboard }) {
  const { progress } = dashboard;
  const percent =
    progress.due_task_count > 0
      ? Math.round(
          (progress.completed_task_count / progress.due_task_count) * 100,
        )
      : progress.completed_task_count > 0
        ? 100
        : 0;

  return (
    <Card>
      <CardHeader className="mb-3">
        <div className="flex items-center justify-between gap-2">
          <CardTitle as="h2" className="flex items-center gap-2.5">
            <span className="flex size-8 items-center justify-center rounded-md bg-brand-primary/12">
              <TrendingUp
                aria-hidden="true"
                className="size-4 text-brand-primary"
              />
            </span>
            My progress
          </CardTitle>
          <Badge variant="neutral">
            {progress.timeframe.starts_on} – {progress.timeframe.ends_on}
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-5">
          <div
            role="img"
            aria-label={`${percent} percent of this week's due tasks completed`}
            className="relative flex size-28 shrink-0 items-center justify-center rounded-full"
            style={{
              background: `conic-gradient(var(--brand-primary) ${percent * 3.6}deg, var(--bg-interactive) 0deg)`,
            }}
          >
            <span className="flex size-22 flex-col items-center justify-center rounded-full bg-bg-surface text-center">
              <span className="text-h3 tabular-nums text-text-primary">
                {percent}%
              </span>
              <span className="px-2 text-caption leading-tight text-text-muted">
                weekly tasks
              </span>
            </span>
          </div>
          <div className="min-w-0 flex-1">
            <ProgressChart dashboard={dashboard} />
          </div>
        </div>

        <p className="text-body text-text-primary">{progress.summary}</p>

        <div className="grid grid-cols-3 gap-2.5">
          <div className="rounded-md border border-border-subtle p-3 text-center">
            <CircleCheck
              aria-hidden="true"
              className="mx-auto size-4 text-status-success"
            />
            <p className="mt-1 text-h4 tabular-nums text-text-primary">
              {progress.completed_task_count}/{progress.due_task_count}
            </p>
            <p className="text-caption text-text-muted">Tasks done</p>
          </div>
          <div className="rounded-md border border-border-subtle p-3 text-center">
            <CalendarClock
              aria-hidden="true"
              className="mx-auto size-4 text-brand-primary"
            />
            <p className="mt-1 text-h4 tabular-nums text-text-primary">
              {progress.focus_minutes}m
            </p>
            <p className="text-caption text-text-muted">Focus time</p>
          </div>
          <div className="rounded-md border border-border-subtle p-3 text-center">
            <Target
              aria-hidden="true"
              className="mx-auto size-4 text-status-deadline"
            />
            <p className="mt-1 text-h4 tabular-nums text-text-primary">
              {Math.max(
                0,
                progress.due_task_count - progress.completed_task_count,
              )}
            </p>
            <p className="text-caption text-text-muted">Still open</p>
          </div>
        </div>

        {progress.next_action ? (
          <p className="flex flex-wrap items-center gap-2 rounded-md border border-brand-primary/30 bg-bg-interactive px-3.5 py-2.5 text-body text-text-primary">
            <span className="font-semibold">Next action:</span>
            <span className="min-w-0 flex-1 truncate">
              {progress.next_action.title}
            </span>
            <Badge
              variant={progress.next_action.kind === "task" ? "deadline" : "ai"}
            >
              {progress.next_action.kind === "task"
                ? "Planner task"
                : "Intake review"}
            </Badge>
          </p>
        ) : null}
      </CardContent>
    </Card>
  );
}

export function RhythmSection({ dashboard }: { dashboard: Dashboard }) {
  const { personal_rhythm: rhythm } = dashboard;

  return (
    <Card>
      <CardHeader className="mb-3">
        <CardTitle as="h2">Personal rhythm</CardTitle>
      </CardHeader>
      <CardContent>
        <ul className="grid grid-cols-7 gap-1.5">
          {rhythm.days.map((day) => (
            <li
              key={day.date}
              className={cn(
                "rounded-md border px-1.5 py-2 text-center",
                day.focus_minutes > 0
                  ? "border-status-research/40 bg-status-research/10"
                  : "border-border-subtle",
              )}
            >
              <p className="text-caption text-text-muted">
                {weekdayLetter(day.date)}
              </p>
              <p
                className={cn(
                  "mt-0.5 text-caption font-medium tabular-nums",
                  day.focus_minutes > 0
                    ? "text-status-research"
                    : "text-text-muted",
                )}
              >
                {day.focus_minutes > 0 ? `${day.focus_minutes}m` : "—"}
              </p>
            </li>
          ))}
        </ul>
        <p className="mt-2 text-caption text-text-muted">
          {rhythm.has_activity
            ? "Focus minutes from your real sessions over the last 7 days."
            : "No focus activity in the last 7 days — this widget only ever shows real minutes."}
        </p>
      </CardContent>
    </Card>
  );
}
