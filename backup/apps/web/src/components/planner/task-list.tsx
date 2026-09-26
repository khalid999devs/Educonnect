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
  ErrorState,
  Select,
  Skeleton,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import {
  Archive,
  CalendarClock,
  CalendarOff,
  ChevronLeft,
  ChevronRight,
  Inbox,
  ListChecks,
  Plus,
  RotateCcw,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";

import type { Course } from "@/lib/api/courses";
import { plannerKeys } from "@/lib/query-keys";
import { listTasks, type Task, type TaskStatus } from "@/lib/api/planner";
import { IconChip } from "@/components/shared/icon-chip";
import { SearchBar } from "@/components/shared/search-bar";
import { courseColor } from "./course-colors";
import {
  DUE_SCOPE_LABELS,
  EMPTY_TASK_FILTERS,
  hasActiveTaskFilters,
  STATUS_FILTER_LABELS,
  taskListParams,
  type TaskDueScope,
  type TaskFilters,
  type TaskStatusFilter,
} from "./task-filters";
import {
  formatDayHeading,
  formatLocalTime,
  localDateOf,
  toApiInstant,
} from "./time";

const STATUS_BADGE: Record<
  TaskStatus,
  { variant: "neutral" | "info" | "success"; label: string }
> = {
  pending: { variant: "neutral", label: "Pending" },
  in_progress: { variant: "info", label: "In progress" },
  completed: { variant: "success", label: "Completed" },
};

/** Entry stagger, capped: past the fourth row the delay stops reading as
 * intentional and starts reading as lag. */
const STAGGER = [
  "motion-safe:[animation-delay:80ms]",
  "motion-safe:[animation-delay:160ms]",
  "motion-safe:[animation-delay:240ms]",
  "motion-safe:[animation-delay:320ms]",
];

export type TaskListProps = {
  /** `archived` swaps the list onto `archive_status=archived` and offers
   * restore instead of archive. */
  mode: "active" | "archived";
  courses: Course[];
  timezone: string;
  now: Date;
  busyTaskId: string | null;
  onEditTask: (task: Task) => void;
  onToggleTask: (task: Task) => void;
  onArchiveTask: (task: Task) => void;
  onRestoreTask: (task: Task) => void;
  onCreateTask: () => void;
};

/**
 * The full task surface the weekly board cannot show.
 *
 * The board reads `GET /planner/weekly`, which requires `due_at` to sit inside
 * the requested window, so undated tasks were unreachable anywhere in the
 * product. This list reads `GET /tasks` directly, exposes the server filters
 * verbatim, and pages with a real cursor in both directions.
 */
export function TaskList({
  mode,
  courses,
  timezone,
  now,
  busyTaskId,
  onEditTask,
  onToggleTask,
  onArchiveTask,
  onRestoreTask,
  onCreateTask,
}: TaskListProps) {
  const archived = mode === "archived";
  /* Filters and the range anchor move together: a due-range filter must be
     computed from one frozen instant, never from a ticking clock. */
  const [query, setQuery] = useState<{ filters: TaskFilters; anchor: string }>(
    () => ({ filters: EMPTY_TASK_FILTERS, anchor: toApiInstant(new Date()) }),
  );
  const [searchDraft, setSearchDraft] = useState("");
  /* Cursor history: the tail is the page being shown, so "previous" is a pop
     rather than a guess at a backwards cursor. */
  const [cursorStack, setCursorStack] = useState<string[]>([]);

  const applyFilters = useCallback((patch: Partial<TaskFilters>) => {
    setQuery((current) => ({
      filters: { ...current.filters, ...patch },
      anchor: toApiInstant(new Date()),
    }));
    setCursorStack((stack) => (stack.length === 0 ? stack : []));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setQuery((current) =>
        current.filters.search === searchDraft
          ? current
          : {
              filters: { ...current.filters, search: searchDraft },
              anchor: toApiInstant(new Date()),
            },
      );
      setCursorStack((stack) => (stack.length === 0 ? stack : []));
    }, 300);

    return () => window.clearTimeout(timer);
  }, [searchDraft]);

  const cursor = cursorStack.at(-1);
  const params = useMemo(
    () => taskListParams(query.filters, archived, query.anchor, cursor),
    [query, archived, cursor],
  );

  const tasksQuery = useQuery({
    queryKey: plannerKeys.tasks(params),
    queryFn: () => listTasks(params),
    /* Keeps the previous page on screen while the next one loads, so paging
       does not flash a skeleton over a list the user is reading. */
    placeholderData: (previous) => previous,
  });

  const tasks = tasksQuery.data?.data ?? [];
  const pagination = tasksQuery.data?.meta.pagination;
  const filtered = hasActiveTaskFilters(query.filters);

  const clearFilters = () => {
    setSearchDraft("");
    setQuery({ filters: EMPTY_TASK_FILTERS, anchor: toApiInstant(new Date()) });
    setCursorStack([]);
  };

  return (
    <Card className="motion-safe:animate-fade-up">
      <CardHeader className="flex flex-wrap items-center justify-between gap-3">
        <CardTitle className="flex items-center gap-2.5">
          <IconChip
            icon={archived ? Archive : ListChecks}
            accent="planner"
            size="md"
          />
          {archived ? "Archived tasks" : "All tasks"}
        </CardTitle>
        {archived ? null : (
          <Button variant="secondary" size="sm" onClick={onCreateTask}>
            <Plus aria-hidden="true" className="size-4" />
            Add task
          </Button>
        )}
      </CardHeader>

      <CardContent className="space-y-4">
        <p className="text-body text-text-secondary">
          {archived
            ? "Archived tasks stay out of your plan and out of the weekly board. Restore one to bring it back."
            : "Everything you are tracking, including tasks with no due date - which never appear on the weekly board or the day agenda."}
        </p>

        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
          <SearchBar
            value={searchDraft}
            onChange={setSearchDraft}
            label="Search tasks by title"
            placeholder="Search tasks…"
            className="sm:col-span-2 xl:col-span-1"
          />
          <Select
            aria-label="Filter by status"
            value={query.filters.status}
            onChange={(event) =>
              applyFilters({
                status: event.target.value as TaskStatusFilter,
              })
            }
          >
            {(Object.keys(STATUS_FILTER_LABELS) as TaskStatusFilter[]).map(
              (value) => (
                <option key={value} value={value}>
                  {STATUS_FILTER_LABELS[value]}
                </option>
              ),
            )}
          </Select>
          <Select
            aria-label="Filter by course"
            value={query.filters.courseId}
            onChange={(event) => applyFilters({ courseId: event.target.value })}
          >
            <option value="">Any course</option>
            {courses.map((course) => (
              <option key={course.id} value={course.id}>
                {course.code
                  ? `${course.code} - ${course.title}`
                  : course.title}
              </option>
            ))}
          </Select>
          <Select
            aria-label="Filter by due date"
            value={query.filters.due}
            onChange={(event) =>
              applyFilters({ due: event.target.value as TaskDueScope })
            }
          >
            {(Object.keys(DUE_SCOPE_LABELS) as TaskDueScope[]).map((value) => (
              <option key={value} value={value}>
                {DUE_SCOPE_LABELS[value]}
              </option>
            ))}
          </Select>
        </div>

        {filtered ? (
          <div className="flex items-center justify-between gap-2">
            <p className="text-caption text-text-muted">
              Filters are applied to the query, not to the page.
            </p>
            <Button variant="ghost" size="sm" onClick={clearFilters}>
              Clear filters
            </Button>
          </div>
        ) : null}

        {tasksQuery.isPending ? (
          <div className="space-y-2" aria-hidden="true">
            <Skeleton className="h-14 rounded-md" />
            <Skeleton className="h-14 rounded-md" />
            <Skeleton className="h-14 rounded-md" />
            <Skeleton className="h-14 rounded-md" />
          </div>
        ) : tasksQuery.isError ? (
          <ErrorState
            title="Your tasks could not load"
            description="Nothing was lost. This is a loading problem, not a data problem."
            onRetry={() => void tasksQuery.refetch()}
          />
        ) : tasks.length === 0 ? (
          <EmptyState
            icon={filtered ? Inbox : archived ? Archive : ListChecks}
            title={
              filtered
                ? "No tasks match these filters"
                : archived
                  ? "Nothing is archived"
                  : "No tasks yet"
            }
            description={
              filtered
                ? "Try a wider due range or clear the filters to see everything."
                : archived
                  ? "Tasks you archive stay recoverable and show up here."
                  : "Add a task with or without a due date. Undated tasks live here until you schedule them."
            }
            action={
              filtered ? (
                <Button variant="secondary" size="sm" onClick={clearFilters}>
                  Clear filters
                </Button>
              ) : archived ? null : (
                <Button size="sm" onClick={onCreateTask}>
                  <Plus aria-hidden="true" className="size-4" />
                  Add task
                </Button>
              )
            }
          />
        ) : (
          <ul className="space-y-2">
            {tasks.map((task, index) => (
              <TaskRow
                key={task.id}
                task={task}
                archived={archived}
                timezone={timezone}
                now={now}
                busy={busyTaskId === task.id}
                stagger={STAGGER[index]}
                onEdit={onEditTask}
                onToggle={onToggleTask}
                onArchive={onArchiveTask}
                onRestore={onRestoreTask}
              />
            ))}
          </ul>
        )}

        {tasks.length > 0 ? (
          <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border-subtle pt-3">
            <p className="text-caption tabular-nums text-text-muted">
              Showing {tasks.length}
              {cursorStack.length > 0
                ? ` on page ${cursorStack.length + 1}`
                : null}
            </p>
            <div className="flex items-center gap-1">
              <Button
                variant="ghost"
                size="sm"
                disabled={cursorStack.length === 0 || tasksQuery.isFetching}
                onClick={() => setCursorStack((stack) => stack.slice(0, -1))}
              >
                <ChevronLeft aria-hidden="true" className="size-4" />
                Previous
              </Button>
              <Button
                variant="ghost"
                size="sm"
                disabled={
                  pagination?.next_cursor == null || tasksQuery.isFetching
                }
                onClick={() =>
                  setCursorStack((stack) =>
                    pagination?.next_cursor == null
                      ? stack
                      : [...stack, pagination.next_cursor],
                  )
                }
              >
                Next
                <ChevronRight aria-hidden="true" className="size-4" />
              </Button>
            </div>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}

type TaskRowProps = {
  task: Task;
  archived: boolean;
  timezone: string;
  now: Date;
  busy: boolean;
  stagger: string | undefined;
  onEdit: (task: Task) => void;
  onToggle: (task: Task) => void;
  onArchive: (task: Task) => void;
  onRestore: (task: Task) => void;
};

function TaskRow({
  task,
  archived,
  timezone,
  now,
  busy,
  stagger,
  onEdit,
  onToggle,
  onArchive,
  onRestore,
}: TaskRowProps) {
  const badge = STATUS_BADGE[task.status];
  const overdue =
    task.due_at !== null &&
    task.status !== "completed" &&
    new Date(task.due_at).getTime() < now.getTime();

  return (
    <li className={cn("motion-safe:animate-fade-up", stagger)}>
      {/* `bg-bg-subtle/60` inside a `bg-bg-surface` card is the established
          planner row treatment (agenda panel, rail cards): it holds a visible
          edge in both themes without relying on a drop shadow. */}
      <div className="flex items-center gap-3 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-2.5 transition-colors hover:border-border-strong">
        {archived ? (
          <IconChip icon={Archive} accent="planner" size="sm" />
        ) : (
          <input
            type="checkbox"
            aria-label={`Mark "${task.title}" ${
              task.status === "completed" ? "not completed" : "completed"
            }`}
            checked={task.status === "completed"}
            disabled={busy}
            onChange={() => onToggle(task)}
            className="size-4 shrink-0 accent-brand-primary"
          />
        )}

        <button
          type="button"
          onClick={() => onEdit(task)}
          className="min-w-0 flex-1 rounded-md text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
        >
          <span
            className={cn(
              "block truncate text-body font-medium",
              task.status === "completed" && !archived
                ? "text-text-muted line-through"
                : "text-text-primary",
            )}
          >
            {task.title}
          </span>
          <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
            {task.course ? (
              <span
                className={cn(
                  "truncate text-caption",
                  courseColor(task.course.id).text,
                )}
              >
                {task.course.code ?? task.course.title}
              </span>
            ) : (
              <span className="text-caption text-text-muted">No course</span>
            )}
            {task.due_at === null ? (
              <span className="inline-flex items-center gap-1 text-caption text-text-muted">
                <CalendarOff aria-hidden="true" className="size-3.5" />
                No due date
              </span>
            ) : (
              <span
                className={cn(
                  "inline-flex items-center gap-1 text-caption tabular-nums",
                  overdue ? "text-status-deadline" : "text-text-muted",
                )}
              >
                <CalendarClock aria-hidden="true" className="size-3.5" />
                {formatDayHeading(
                  localDateOf(new Date(task.due_at), timezone),
                )}{" "}
                at {formatLocalTime(task.due_at, timezone)}
                {overdue ? " (overdue)" : null}
              </span>
            )}
          </span>
        </button>

        <Badge
          variant={badge.variant}
          className="hidden shrink-0 sm:inline-flex"
        >
          {badge.label}
        </Badge>

        {archived ? (
          <Button
            variant="secondary"
            size="sm"
            disabled={busy}
            onClick={() => onRestore(task)}
          >
            <RotateCcw aria-hidden="true" className="size-4" />
            Restore
          </Button>
        ) : (
          <Button
            variant="ghost"
            size="sm"
            aria-label={`Archive "${task.title}"`}
            disabled={busy}
            onClick={() => onArchive(task)}
          >
            <Archive aria-hidden="true" className="size-4" />
          </Button>
        )}
      </div>
    </li>
  );
}
