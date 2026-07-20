"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  EmptyState,
  Skeleton,
} from "@educonnect/ui";
import {
  CalendarDays,
  CalendarRange,
  ChevronLeft,
  ChevronRight,
  Flag,
  List,
} from "lucide-react";
import { useMemo, useState } from "react";

import type { FocusSession, PlannerWeekly, Task } from "@/lib/api/planner";
import { courseColor } from "./course-colors";
import {
  addDays,
  durationMinutes,
  formatDayHeading,
  formatLocalTime,
  formatLocalTimeRange,
  formatWeekRange,
  isOnLocalDate,
  minutesIntoDay,
  weekDates,
  weekdayShort,
} from "./time";

const DAY_START_MINUTE = 7 * 60;
const DAY_END_MINUTE = 21 * 60;
const DAY_RANGE = DAY_END_MINUTE - DAY_START_MINUTE;
const HOUR_MARKS = [8, 10, 12, 14, 16, 18, 20];

type BoardMode = "grid" | "list";

export type WeeklyBoardProps = {
  weekly: PlannerWeekly | undefined;
  loading: boolean;
  timezone: string;
  today: string;
  weekStart: string;
  onWeekChange: (weekStart: string) => void;
  currentWeekStart: string;
  onEditTask: (task: Task) => void;
  onEditSession: (session: FocusSession) => void;
  onToggleTask: (task: Task) => void;
  busyTaskId: string | null;
};

type DayLane = {
  date: string;
  sessions: FocusSession[];
  tasks: Task[];
};

function clampPercent(minute: number): number {
  const bounded = Math.min(Math.max(minute, DAY_START_MINUTE), DAY_END_MINUTE);

  return ((bounded - DAY_START_MINUTE) / DAY_RANGE) * 100;
}

export function WeeklyBoard({
  weekly,
  loading,
  timezone,
  today,
  weekStart,
  onWeekChange,
  currentWeekStart,
  onEditTask,
  onEditSession,
  onToggleTask,
  busyTaskId,
}: WeeklyBoardProps) {
  const [mode, setMode] = useState<BoardMode>("grid");

  const dates = useMemo(() => weekDates(weekStart), [weekStart]);

  const lanes: DayLane[] = useMemo(() => {
    const sessions = weekly?.data.focus_sessions ?? [];
    const tasks = (weekly?.data.tasks ?? []).filter(
      (task) => task.due_at !== null,
    );

    return dates.map((date) => ({
      date,
      sessions: sessions
        .filter((session) => isOnLocalDate(session.starts_at, date, timezone))
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
      tasks: tasks
        .filter((task) => isOnLocalDate(task.due_at as string, date, timezone))
        .sort((a, b) => (a.due_at as string).localeCompare(b.due_at as string)),
    }));
  }, [weekly, dates, timezone]);

  const legendCourses = useMemo(() => {
    const seen = new Map<string, { id: string; label: string }>();

    for (const lane of lanes) {
      for (const session of lane.sessions) {
        if (session.course && !seen.has(session.course.id)) {
          seen.set(session.course.id, {
            id: session.course.id,
            label: session.course.code ?? session.course.title,
          });
        }
      }

      for (const task of lane.tasks) {
        if (task.course && !seen.has(task.course.id)) {
          seen.set(task.course.id, {
            id: task.course.id,
            label: task.course.code ?? task.course.title,
          });
        }
      }
    }

    return Array.from(seen.values());
  }, [lanes]);

  const hasAnything = lanes.some(
    (lane) => lane.sessions.length > 0 || lane.tasks.length > 0,
  );
  const hasMore =
    weekly !== undefined &&
    (weekly.meta.has_more.tasks || weekly.meta.has_more.focus_sessions);

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-center justify-between gap-3">
        <CardTitle className="flex items-center gap-2">
          <CalendarRange
            aria-hidden="true"
            className="size-5 text-brand-primary"
          />
          Weekly schedule
        </CardTitle>
        <div className="flex flex-wrap items-center gap-2">
          <p className="text-body text-text-muted">
            {formatWeekRange(weekStart)}
          </p>
          <div className="flex items-center gap-1">
            <Button
              variant="ghost"
              size="sm"
              aria-label="Previous week"
              onClick={() => onWeekChange(addDays(weekStart, -7))}
            >
              <ChevronLeft aria-hidden="true" className="size-4" />
            </Button>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => onWeekChange(currentWeekStart)}
              disabled={weekStart === currentWeekStart}
            >
              Today
            </Button>
            <Button
              variant="ghost"
              size="sm"
              aria-label="Next week"
              onClick={() => onWeekChange(addDays(weekStart, 7))}
            >
              <ChevronRight aria-hidden="true" className="size-4" />
            </Button>
          </div>
          <div
            role="group"
            aria-label="Schedule layout"
            className="flex items-center rounded-md border border-border-default p-0.5"
          >
            <Button
              variant={mode === "grid" ? "secondary" : "ghost"}
              size="sm"
              aria-pressed={mode === "grid"}
              onClick={() => setMode("grid")}
            >
              <CalendarDays aria-hidden="true" className="size-4" />
              <span className="sr-only sm:not-sr-only sm:ml-1.5">Grid</span>
            </Button>
            <Button
              variant={mode === "list" ? "secondary" : "ghost"}
              size="sm"
              aria-pressed={mode === "list"}
              onClick={() => setMode("list")}
            >
              <List aria-hidden="true" className="size-4" />
              <span className="sr-only sm:not-sr-only sm:ml-1.5">List</span>
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {loading ? (
          <Skeleton className="h-96 rounded-lg" />
        ) : !hasAnything ? (
          <EmptyState
            icon={CalendarRange}
            title="Nothing planned this week"
            description="Add a task with a due date or log a focus session to see it here."
          />
        ) : mode === "grid" ? (
          <div className="overflow-x-auto pb-1" tabIndex={-1}>
            <div className="min-w-200">
              <div className="grid grid-cols-[3rem_repeat(7,minmax(0,1fr))] gap-x-1">
                <span aria-hidden="true" />
                {lanes.map((lane) => (
                  <p
                    key={lane.date}
                    className={cn(
                      "rounded-md px-1 py-1.5 text-center text-caption font-medium",
                      lane.date === today
                        ? "bg-bg-interactive text-brand-primary"
                        : "text-text-muted",
                    )}
                  >
                    {weekdayShort(lane.date)}{" "}
                    <span className="tabular-nums">
                      {Number(lane.date.slice(8, 10))}
                    </span>
                  </p>
                ))}
              </div>
              <div className="mt-1 grid grid-cols-[3rem_repeat(7,minmax(0,1fr))] gap-x-1">
                <div className="relative h-140" aria-hidden="true">
                  {HOUR_MARKS.map((hour) => (
                    <span
                      key={hour}
                      className="absolute right-1.5 -translate-y-1/2 text-caption tabular-nums text-text-muted"
                      style={{ top: `${clampPercent(hour * 60)}%` }}
                    >
                      {hour <= 12 ? hour : hour - 12}
                      {hour < 12 ? "am" : "pm"}
                    </span>
                  ))}
                </div>
                {lanes.map((lane) => (
                  <div
                    key={lane.date}
                    className={cn(
                      "relative h-140 rounded-md border border-border-subtle",
                      lane.date === today
                        ? "bg-bg-interactive/40"
                        : "bg-bg-subtle/40",
                    )}
                  >
                    {HOUR_MARKS.map((hour) => (
                      <span
                        key={hour}
                        aria-hidden="true"
                        className="absolute inset-x-0 border-t border-border-subtle"
                        style={{ top: `${clampPercent(hour * 60)}%` }}
                      />
                    ))}
                    {lane.sessions.map((session, index) => {
                      const startMinute = minutesIntoDay(
                        session.starts_at,
                        timezone,
                      );
                      const top = clampPercent(startMinute);
                      const bottom = clampPercent(
                        startMinute +
                          durationMinutes(session.starts_at, session.ends_at),
                      );
                      const color = courseColor(session.course?.id);
                      const label =
                        session.task?.title ??
                        session.course?.title ??
                        "Focus session";

                      return (
                        <button
                          key={session.id}
                          type="button"
                          onClick={() => onEditSession(session)}
                          className={cn(
                            "absolute inset-x-0.5 overflow-hidden rounded-md border-l-2 px-1.5 py-1 text-left transition-opacity hover:opacity-85 focus-visible:outline-2 focus-visible:outline-brand-focus",
                            color.block,
                            color.edge,
                          )}
                          style={{
                            top: `${top}%`,
                            height: `${Math.max(bottom - top, 4)}%`,
                            marginLeft: `${Math.min(index, 2) * 6}%`,
                          }}
                        >
                          <span className="block truncate text-caption font-medium text-text-primary">
                            {label}
                          </span>
                          <span className="block truncate text-caption text-text-muted">
                            {formatLocalTime(session.starts_at, timezone)}
                          </span>
                        </button>
                      );
                    })}
                    {lane.tasks.map((task) => {
                      const color = courseColor(task.course?.id);

                      return (
                        <button
                          key={task.id}
                          type="button"
                          onClick={() => onEditTask(task)}
                          className={cn(
                            "absolute inset-x-0.5 z-10 flex items-center gap-1 truncate rounded-sm px-1 text-left text-caption transition-opacity hover:opacity-85 focus-visible:outline-2 focus-visible:outline-brand-focus",
                            task.status === "completed"
                              ? "text-text-muted line-through"
                              : "text-text-secondary",
                          )}
                          style={{
                            top: `calc(${clampPercent(
                              minutesIntoDay(task.due_at as string, timezone),
                            )}% - 0.5rem)`,
                          }}
                        >
                          <Flag
                            aria-hidden="true"
                            className={cn("size-3 shrink-0", color.text)}
                          />
                          <span className="truncate">{task.title}</span>
                        </button>
                      );
                    })}
                  </div>
                ))}
              </div>
            </div>
          </div>
        ) : (
          <ol className="space-y-4">
            {lanes.map((lane) => (
              <li key={lane.date}>
                <p
                  className={cn(
                    "mb-1.5 text-caption font-semibold uppercase tracking-wide",
                    lane.date === today
                      ? "text-brand-primary"
                      : "text-text-muted",
                  )}
                >
                  {formatDayHeading(lane.date)}
                  {lane.date === today ? " · Today" : ""}
                </p>
                {lane.sessions.length === 0 && lane.tasks.length === 0 ? (
                  <p className="text-body text-text-muted">Nothing planned.</p>
                ) : (
                  <ul className="space-y-1.5">
                    {lane.sessions.map((session) => {
                      const color = courseColor(session.course?.id);

                      return (
                        <li key={session.id}>
                          <button
                            type="button"
                            onClick={() => onEditSession(session)}
                            className="flex w-full items-center gap-2 rounded-md border border-border-subtle bg-bg-subtle/60 px-2.5 py-2 text-left hover:border-border-strong focus-visible:outline-2 focus-visible:outline-brand-focus"
                          >
                            <span
                              aria-hidden="true"
                              className={cn(
                                "size-2 shrink-0 rounded-full",
                                color.dot,
                              )}
                            />
                            <span className="min-w-0 flex-1 truncate text-body text-text-primary">
                              {session.task?.title ??
                                session.course?.title ??
                                "Focus session"}
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
                    {lane.tasks.map((task) => (
                      <li key={task.id} className="flex items-center gap-2">
                        <input
                          type="checkbox"
                          aria-label={`Mark "${task.title}" ${
                            task.status === "completed"
                              ? "not completed"
                              : "completed"
                          }`}
                          checked={task.status === "completed"}
                          disabled={busyTaskId === task.id}
                          onChange={() => onToggleTask(task)}
                          className="size-4 shrink-0 accent-brand-primary"
                        />
                        <button
                          type="button"
                          onClick={() => onEditTask(task)}
                          className={cn(
                            "flex min-w-0 flex-1 items-center justify-between gap-2 rounded-md px-1.5 py-1 text-left hover:bg-bg-subtle focus-visible:outline-2 focus-visible:outline-brand-focus",
                          )}
                        >
                          <span
                            className={cn(
                              "truncate text-body",
                              task.status === "completed"
                                ? "text-text-muted line-through"
                                : "text-text-primary",
                            )}
                          >
                            {task.title}
                          </span>
                          <span className="shrink-0 text-caption tabular-nums text-text-muted">
                            due{" "}
                            {formatLocalTime(task.due_at as string, timezone)}
                          </span>
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ol>
        )}

        {legendCourses.length > 0 ? (
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-border-subtle pt-3">
            {legendCourses.map((course) => {
              const color = courseColor(course.id);

              return (
                <span
                  key={course.id}
                  className="flex items-center gap-1.5 text-caption text-text-muted"
                >
                  <span
                    aria-hidden="true"
                    className={cn("size-2 rounded-full", color.dot)}
                  />
                  {course.label}
                </span>
              );
            })}
          </div>
        ) : null}

        {hasMore ? (
          <Badge variant="warning">
            Showing the first {weekly?.meta.limit} items. Narrow the week to see
            everything.
          </Badge>
        ) : null}
      </CardContent>
    </Card>
  );
}
