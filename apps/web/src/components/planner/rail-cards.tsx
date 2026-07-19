"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
} from "@educonnect/ui";
import { CalendarClock, Target } from "lucide-react";

import type { FocusSession, Task } from "@/lib/api/planner";
import { courseColor } from "./course-colors";
import {
  durationMinutes,
  formatDayHeading,
  formatLocalTime,
  formatLocalTimeRange,
  localDateOf,
} from "./time";

/** Next uncompleted deadline within the loaded week - honestly scoped. */
export function UpcomingDeadlineCard({
  tasks,
  timezone,
  now,
  onEditTask,
}: {
  tasks: Task[];
  timezone: string;
  now: Date;
  onEditTask: (task: Task) => void;
}) {
  const next = tasks
    .filter(
      (task) =>
        task.status !== "completed" &&
        task.due_at !== null &&
        new Date(task.due_at).getTime() >= now.getTime(),
    )
    .sort((a, b) => (a.due_at as string).localeCompare(b.due_at as string))[0];

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <CalendarClock
            aria-hidden="true"
            className="size-5 text-status-deadline"
          />
          Upcoming deadline
        </CardTitle>
      </CardHeader>
      <CardContent>
        {next ? (
          <button
            type="button"
            onClick={() => onEditTask(next)}
            className="flex w-full items-center justify-between gap-3 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-2.5 text-left hover:border-border-strong focus-visible:outline-2 focus-visible:outline-brand-focus"
          >
            <span className="min-w-0">
              <span className="block truncate text-body font-medium text-text-primary">
                {next.title}
              </span>
              {next.course ? (
                <span
                  className={cn(
                    "block truncate text-caption",
                    courseColor(next.course.id).text,
                  )}
                >
                  {next.course.code ?? next.course.title}
                </span>
              ) : null}
            </span>
            <span className="shrink-0 text-right">
              <span className="block text-caption font-medium text-status-deadline">
                {formatDayHeading(
                  localDateOf(new Date(next.due_at as string), timezone),
                )}
              </span>
              <span className="block text-caption tabular-nums text-text-muted">
                {formatLocalTime(next.due_at as string, timezone)}
              </span>
            </span>
          </button>
        ) : (
          <p className="text-body text-text-muted">
            No open deadlines left this week.
          </p>
        )}
      </CardContent>
    </Card>
  );
}

/** The running or next focus session today, plus the honest way to plan
 * one - sessions are records with real start and end times. */
export function FocusSessionCard({
  sessions,
  timezone,
  now,
  onEditSession,
  onLogSession,
}: {
  sessions: FocusSession[];
  timezone: string;
  now: Date;
  onEditSession: (session: FocusSession) => void;
  onLogSession: () => void;
}) {
  const running = sessions.find(
    (session) =>
      new Date(session.starts_at).getTime() <= now.getTime() &&
      now.getTime() < new Date(session.ends_at).getTime(),
  );
  const upcoming = sessions
    .filter((session) => new Date(session.starts_at).getTime() >= now.getTime())
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at))[0];
  const shown = running ?? upcoming;

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2">
          <Target aria-hidden="true" className="size-5 text-status-success" />
          Focus session
        </CardTitle>
        {running ? <Badge variant="success">In progress</Badge> : null}
      </CardHeader>
      <CardContent className="space-y-3">
        {shown ? (
          <button
            type="button"
            onClick={() => onEditSession(shown)}
            className="flex w-full items-center justify-between gap-3 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-2.5 text-left hover:border-border-strong focus-visible:outline-2 focus-visible:outline-brand-focus"
          >
            <span className="min-w-0">
              <span className="block truncate text-body font-medium text-text-primary">
                {shown.task?.title ?? shown.course?.title ?? "Focus session"}
              </span>
              <span className="block text-caption text-text-muted">
                {durationMinutes(shown.starts_at, shown.ends_at)} min ·{" "}
                {shown.note ? "with note" : "distraction free"}
              </span>
            </span>
            <span className="shrink-0 text-caption tabular-nums text-text-muted">
              {formatLocalTimeRange(shown.starts_at, shown.ends_at, timezone)}
            </span>
          </button>
        ) : (
          <p className="text-body text-text-muted">
            No focus session planned today.
          </p>
        )}
        <Button variant="secondary" size="md" onClick={onLogSession}>
          Log focus session
        </Button>
      </CardContent>
    </Card>
  );
}
