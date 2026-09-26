/**
 * The task-list filter model.
 *
 * Every filter here maps onto a parameter `GET /tasks` has always accepted and
 * that no UI ever exposed (`ListTasksRequest`: search, status, archive_status,
 * course_id, due_from, due_before, has_due). Filtering happens on the server;
 * nothing is narrowed client-side, so a filtered page is a real page and its
 * cursor is a real cursor.
 *
 * Two server rules are honoured by construction and must stay that way:
 *  - `has_due=false` may never be combined with a due range (422).
 *  - `due_before` must be strictly after `due_from` (422).
 */

import type { TaskListParams, TaskStatus } from "@/lib/api/planner";
import { toApiInstant } from "./time";

/**
 * `undated` is the one that matters: `ReadPlannerWindow` requires `due_at` to
 * fall inside the requested window, so a task with no due date cannot appear
 * on the weekly board or the agenda at all. `has_due=false` is the only way to
 * reach it.
 */
export type TaskDueScope =
  "any" | "undated" | "dated" | "overdue" | "next-seven-days";

export type TaskStatusFilter = TaskStatus | "all";

export type TaskFilters = {
  search: string;
  status: TaskStatusFilter;
  /** Empty string means every course, including tasks with no course. */
  courseId: string;
  due: TaskDueScope;
};

export const EMPTY_TASK_FILTERS: TaskFilters = {
  search: "",
  status: "all",
  courseId: "",
  due: "any",
};

export const TASK_PAGE_SIZE = 20;

const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;

export function hasActiveTaskFilters(filters: TaskFilters): boolean {
  return (
    filters.search.trim() !== "" ||
    filters.status !== "all" ||
    filters.courseId !== "" ||
    filters.due !== "any"
  );
}

export const DUE_SCOPE_LABELS: Record<TaskDueScope, string> = {
  any: "Any due date",
  undated: "No due date",
  dated: "Has a due date",
  overdue: "Overdue",
  "next-seven-days": "Due in the next 7 days",
};

export const STATUS_FILTER_LABELS: Record<TaskStatusFilter, string> = {
  all: "Any status",
  pending: "Pending",
  in_progress: "In progress",
  completed: "Completed",
};

/**
 * `anchor` is a frozen second-precision instant captured when the filter last
 * changed. Deriving the range from a ticking clock instead would mint a new
 * query key every minute and refetch the list forever.
 */
export function taskListParams(
  filters: TaskFilters,
  archived: boolean,
  anchor: string,
  cursor?: string,
): TaskListParams {
  const params: TaskListParams = {
    archiveStatus: archived ? "archived" : "active",
    status: filters.status,
    sort: "-updated_at",
    perPage: TASK_PAGE_SIZE,
  };
  const search = filters.search.trim();

  if (search !== "") {
    params.search = search;
  }

  if (filters.courseId !== "") {
    params.courseId = filters.courseId;
  }

  switch (filters.due) {
    case "undated":
      params.hasDue = false;
      break;
    case "dated":
      params.hasDue = true;
      break;
    case "overdue":
      params.dueBefore = anchor;
      break;
    case "next-seven-days":
      params.dueFrom = anchor;
      params.dueBefore = toApiInstant(
        new Date(new Date(anchor).getTime() + SEVEN_DAYS_MS),
      );
      break;
    default:
      break;
  }

  if (cursor !== undefined) {
    params.cursor = cursor;
  }

  return params;
}
