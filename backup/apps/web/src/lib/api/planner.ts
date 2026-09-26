import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/** Exact runtime shapes of the planner contract (tasks, focus sessions,
 * agenda/weekly windows) from openapi.yaml. */

const isoDateTime = z.string();

export const taskStatusSchema = z.enum(["pending", "in_progress", "completed"]);

export type TaskStatus = z.infer<typeof taskStatusSchema>;

export const plannerCourseRefSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  title: z.string(),
  code: z.string().nullable(),
  archive_status: z.enum(["active", "archived"]),
});

export type PlannerCourseRef = z.infer<typeof plannerCourseRefSchema>;

export const taskSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  title: z.string(),
  description: z.string().nullable(),
  course: plannerCourseRefSchema.nullable(),
  due_at: isoDateTime.nullable(),
  status: taskStatusSchema,
  completed_at: isoDateTime.nullable(),
  archive_status: z.enum(["active", "archived"]),
  archived_at: isoDateTime.nullable(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type Task = z.infer<typeof taskSchema>;

export const focusSessionSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  task: z
    .object({
      id: z.string(),
      version: z.number().int().min(1),
      title: z.string(),
      status: taskStatusSchema,
      archive_status: z.enum(["active", "archived"]),
    })
    .nullable(),
  course: plannerCourseRefSchema.nullable(),
  starts_at: isoDateTime,
  ends_at: isoDateTime,
  note: z.string().nullable(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type FocusSession = z.infer<typeof focusSessionSchema>;

const cursorPaginationSchema = z.object({
  next_cursor: z.string().nullable(),
  previous_cursor: z.string().nullable(),
  per_page: z.number().int(),
});

export type CursorPagination = z.infer<typeof cursorPaginationSchema>;

const taskCollectionSchema = z.object({
  data: z.array(taskSchema),
  meta: z.object({ pagination: cursorPaginationSchema }),
});

const focusSessionCollectionSchema = z.object({
  data: z.array(focusSessionSchema),
  meta: z.object({ pagination: cursorPaginationSchema }),
});

const plannerWindowMetaSchema = z.object({
  summary: z.object({
    tasks: z.number().int(),
    focus_sessions: z.number().int(),
  }),
  has_more: z.object({ tasks: z.boolean(), focus_sessions: z.boolean() }),
  limit: z.number().int(),
});

const plannerWindowBaseSchema = z.object({
  timezone: z.string(),
  window: z.object({ starts_at: isoDateTime, ends_at: isoDateTime }),
  tasks: z.array(taskSchema),
  focus_sessions: z.array(focusSessionSchema),
});

const agendaEnvelopeSchema = z.object({
  data: plannerWindowBaseSchema.extend({ date: z.string() }),
  meta: plannerWindowMetaSchema,
});

const weeklyEnvelopeSchema = z.object({
  data: plannerWindowBaseSchema.extend({ week_start: z.string() }),
  meta: plannerWindowMetaSchema,
});

export type PlannerAgenda = z.infer<typeof agendaEnvelopeSchema>;
export type PlannerWeekly = z.infer<typeof weeklyEnvelopeSchema>;

export type TaskListParams = {
  search?: string;
  status?: TaskStatus | "all";
  archiveStatus?: "active" | "archived" | "all";
  courseId?: string;
  dueFrom?: string;
  dueBefore?: string;
  hasDue?: boolean;
  sort?: "updated_at" | "-updated_at";
  perPage?: number;
  cursor?: string;
};

export async function listTasks(
  params: TaskListParams = {},
): Promise<z.infer<typeof taskCollectionSchema>> {
  const query = toQueryString({
    search: params.search,
    status: params.status,
    archive_status: params.archiveStatus,
    course_id: params.courseId,
    due_from: params.dueFrom,
    due_before: params.dueBefore,
    has_due: params.hasDue,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return taskCollectionSchema.parse(await apiFetch(`/api/v1/tasks${query}`));
}

export type TaskWriteInput = {
  title: string;
  description?: string | null;
  course_id?: string | null;
  due_at?: string | null;
};

export async function createTask(input: TaskWriteInput): Promise<Task> {
  return taskSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/tasks", { method: "POST", body: input }),
    ),
  );
}

export async function getTask(taskId: string): Promise<Task> {
  return taskSchema.parse(
    envelopeData(await apiFetch(`/api/v1/tasks/${taskId}`)),
  );
}

export async function updateTask(
  taskId: string,
  input: TaskWriteInput & { expected_version: number },
): Promise<Task> {
  return taskSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/tasks/${taskId}`, { method: "PUT", body: input }),
    ),
  );
}

export async function deleteTask(
  taskId: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/tasks/${taskId}`, {
    method: "DELETE",
    body: { expected_version: expectedVersion },
  });
}

export async function updateTaskStatus(
  taskId: string,
  expectedVersion: number,
  status: TaskStatus,
): Promise<Task> {
  return taskSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/tasks/${taskId}/status`, {
        method: "PUT",
        body: { expected_version: expectedVersion, status },
      }),
    ),
  );
}

export async function archiveTask(
  taskId: string,
  expectedVersion: number,
): Promise<Task> {
  return taskSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/tasks/${taskId}/archive`, {
        method: "PUT",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}

export async function restoreTask(
  taskId: string,
  expectedVersion: number,
): Promise<Task> {
  return taskSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/tasks/${taskId}/archive`, {
        method: "DELETE",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}

/**
 * Completing from the dashboard: the aggregate deliberately omits versions,
 * so read the task's current version first, then apply the status change
 * with optimistic concurrency.
 */
export async function completeTask(taskId: string): Promise<void> {
  const shown = await getTask(taskId);

  await updateTaskStatus(taskId, shown.version, "completed");
}

export type FocusSessionListParams = {
  taskId?: string;
  courseId?: string;
  overlapFrom?: string;
  overlapBefore?: string;
  sort?: "starts_at" | "-starts_at";
  perPage?: number;
  cursor?: string;
};

export async function listFocusSessions(
  params: FocusSessionListParams = {},
): Promise<z.infer<typeof focusSessionCollectionSchema>> {
  const query = toQueryString({
    task_id: params.taskId,
    course_id: params.courseId,
    overlap_from: params.overlapFrom,
    overlap_before: params.overlapBefore,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return focusSessionCollectionSchema.parse(
    await apiFetch(`/api/v1/focus-sessions${query}`),
  );
}

export type FocusSessionWriteInput = {
  task_id?: string | null;
  course_id?: string | null;
  starts_at: string;
  ends_at: string;
  note?: string | null;
};

export async function createFocusSession(
  input: FocusSessionWriteInput,
): Promise<FocusSession> {
  return focusSessionSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/focus-sessions", { method: "POST", body: input }),
    ),
  );
}

export async function updateFocusSession(
  sessionId: string,
  input: FocusSessionWriteInput & { expected_version: number },
): Promise<FocusSession> {
  return focusSessionSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/focus-sessions/${sessionId}`, {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

export async function deleteFocusSession(
  sessionId: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/focus-sessions/${sessionId}`, {
    method: "DELETE",
    body: { expected_version: expectedVersion },
  });
}

export async function getAgenda(
  timezone: string,
  date: string,
  limit?: number,
): Promise<PlannerAgenda> {
  const query = toQueryString({ timezone, date, limit });

  return agendaEnvelopeSchema.parse(
    await apiFetch(`/api/v1/planner/agenda${query}`),
  );
}

export async function getWeekly(
  timezone: string,
  weekStart: string,
  limit?: number,
): Promise<PlannerWeekly> {
  const query = toQueryString({ timezone, week_start: weekStart, limit });

  return weeklyEnvelopeSchema.parse(
    await apiFetch(`/api/v1/planner/weekly${query}`),
  );
}
