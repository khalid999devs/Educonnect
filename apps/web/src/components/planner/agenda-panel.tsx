"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  Skeleton,
} from "@educonnect/ui";
import { CalendarCheck2, ChevronLeft, ChevronRight, Timer } from "lucide-react";

import type { FocusSession, PlannerAgenda, Task } from "@/lib/api/planner";
import { courseColor } from "./course-colors";
import {
  addDays,
  durationMinutes,
  formatDayHeading,
  formatLocalTime,
  formatLocalTimeRange,
} from "./time";

export type AgendaPanelProps = {
  agenda: PlannerAgenda | undefined;
  loading: boolean;
  timezone: string;
  date: string;
  today: string;
  onDateChange: (date: string) => void;
  onToggleTask: (task: Task) => void;
  onEditTask: (task: Task) => void;
  onEditSession: (session: FocusSession) => void;
  busyTaskId: string | null;
};

/** Day agenda with date navigation: the tasks due and focus time planned on
 * one local day, completable in place. */
export function AgendaPanel({
  agenda,
  loading,
  timezone,
  date,
  today,
  onDateChange,
  onToggleTask,
  onEditTask,
  onEditSession,
  busyTaskId,
}: AgendaPanelProps) {
  const tasks = agenda?.data.tasks ?? [];
  const sessions = agenda?.data.focus_sessions ?? [];
  const hasMore =
    agenda !== undefined &&
    (agenda.meta.has_more.tasks || agenda.meta.has_more.focus_sessions);

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2">
          <CalendarCheck2
            aria-hidden="true"
            className="size-5 text-brand-primary"
          />
          {date === today ? "Today's agenda" : "Agenda"}
        </CardTitle>
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            aria-label="Previous day"
            onClick={() => onDateChange(addDays(date, -1))}
          >
            <ChevronLeft aria-hidden="true" className="size-4" />
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => onDateChange(today)}
            disabled={date === today}
          >
            Today
          </Button>
          <Button
            variant="ghost"
            size="sm"
            aria-label="Next day"
            onClick={() => onDateChange(addDays(date, 1))}
          >
            <ChevronRight aria-hidden="true" className="size-4" />
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-body font-medium text-text-secondary">
          {formatDayHeading(date)}
        </p>

        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-12 rounded-md" />
            <Skeleton className="h-12 rounded-md" />
            <Skeleton className="h-12 rounded-md" />
          </div>
        ) : tasks.length === 0 && sessions.length === 0 ? (
          <p className="text-body text-text-muted">
            Nothing due and no focus time planned for this day.
          </p>
        ) : (
          <ul className="space-y-1.5">
            {tasks.map((task) => (
              <li key={task.id} className="flex items-center gap-2.5">
                <input
                  type="checkbox"
                  aria-label={`Mark "${task.title}" ${
                    task.status === "completed" ? "not completed" : "completed"
                  }`}
                  checked={task.status === "completed"}
                  disabled={busyTaskId === task.id}
                  onChange={() => onToggleTask(task)}
                  className="size-4 shrink-0 accent-brand-primary"
                />
                <button
                  type="button"
                  onClick={() => onEditTask(task)}
                  className="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-md px-1.5 py-1.5 text-left hover:bg-bg-subtle focus-visible:outline-2 focus-visible:outline-brand-focus"
                >
                  <span className="min-w-0">
                    <span
                      className={cn(
                        "block truncate text-body",
                        task.status === "completed"
                          ? "text-text-muted line-through"
                          : "text-text-primary",
                      )}
                    >
                      {task.title}
                    </span>
                    {task.course ? (
                      <span
                        className={cn(
                          "block truncate text-caption",
                          courseColor(task.course.id).text,
                        )}
                      >
                        {task.course.code ?? task.course.title}
                      </span>
                    ) : null}
                  </span>
                  {task.due_at ? (
                    <span className="shrink-0 text-caption tabular-nums text-text-muted">
                      {formatLocalTime(task.due_at, timezone)}
                    </span>
                  ) : null}
                </button>
              </li>
            ))}
            {sessions.map((session) => {
              const color = courseColor(session.course?.id);

              return (
                <li key={session.id}>
                  <button
                    type="button"
                    onClick={() => onEditSession(session)}
                    className="flex w-full items-center gap-2.5 rounded-md border border-border-subtle bg-bg-subtle/60 px-2.5 py-2 text-left hover:border-border-strong focus-visible:outline-2 focus-visible:outline-brand-focus"
                  >
                    <Timer
                      aria-hidden="true"
                      className={cn("size-4 shrink-0", color.text)}
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-body text-text-primary">
                        {session.task?.title ??
                          session.course?.title ??
                          "Focus session"}
                      </span>
                      <span className="block text-caption text-text-muted">
                        {durationMinutes(session.starts_at, session.ends_at)}{" "}
                        min focus
                      </span>
                    </span>
                    <span className="shrink-0 text-caption tabular-nums text-text-muted">
                      {formatLocalTimeRange(
                        session.starts_at,
                        session.ends_at,
                        timezone,
                      )}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        )}

        {hasMore ? (
          <Badge variant="warning">
            Showing the first {agenda?.meta.limit} items for this day.
          </Badge>
        ) : null}
      </CardContent>
    </Card>
  );
}
