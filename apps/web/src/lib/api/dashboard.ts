import { z } from "zod";

import { apiFetch, envelopeData } from "./http";

/** Exact runtime shape of GET /api/v1/dashboard (Phase 17 contract). */

const isoDate = z.string();
const isoDateTime = z.string().nullable();

export const dashboardTaskSchema = z.object({
  id: z.string(),
  title: z.string(),
  status: z.enum(["pending", "in_progress"]),
  due_at: isoDateTime,
  overdue: z.boolean(),
  course: z.object({ id: z.string(), title: z.string() }).nullable(),
});

export type DashboardTask = z.infer<typeof dashboardTaskSchema>;

export const dashboardSchema = z.object({
  timeframe: z.object({
    timezone: z.string(),
    today: isoDate,
    week_starts_on: isoDate,
    week_ends_on: isoDate,
  }),
  cover: z.object({
    name: z.string(),
    institution: z.string().nullable(),
    degree: z.string().nullable(),
    major: z.string().nullable(),
    study_stage: z.string().nullable(),
    term: z.object({ id: z.string(), label: z.string() }).nullable(),
    active_course_count: z.number().int(),
  }),
  quick_intake: z.object({
    active_item: z
      .object({
        id: z.string(),
        state: z.string(),
        failure_code: z.string().nullable(),
        submitted_at: isoDateTime,
      })
      .nullable(),
    awaiting_review_count: z.number().int(),
  }),
  whats_next: z.object({
    tasks: z.array(dashboardTaskSchema),
    overdue_count: z.number().int(),
    upcoming_count: z.number().int(),
  }),
  tools: z.array(
    z.object({
      id: z.string(),
      name: z.string(),
      category: z.string().nullable(),
      saved: z.boolean(),
    }),
  ),
  today: z.object({
    due_task_count: z.number().int(),
    due_tasks: z.array(
      z.object({ id: z.string(), title: z.string(), due_at: isoDateTime }),
    ),
    next_focus_session: z
      .object({
        id: z.string(),
        starts_at: isoDateTime,
        ends_at: isoDateTime,
        in_progress: z.boolean(),
        task_title: z.string().nullable(),
        course_title: z.string().nullable(),
      })
      .nullable(),
  }),
  second_brain: z.object({
    total_item_count: z.number().int(),
    recent_items: z.array(
      z.object({
        id: z.string(),
        title: z.string(),
        source_type: z.string(),
        updated_at: isoDateTime,
      }),
    ),
  }),
  progress: z.object({
    timeframe: z.object({
      timezone: z.string(),
      starts_on: isoDate,
      ends_on: isoDate,
    }),
    completed_task_count: z.number().int(),
    due_task_count: z.number().int(),
    focus_minutes: z.number().int(),
    daily_completed: z.array(
      z.object({ date: isoDate, completed: z.number().int() }),
    ),
    summary: z.string(),
    next_action: z
      .object({
        kind: z.enum(["task", "intake_review"]),
        id: z.string(),
        title: z.string(),
        due_at: isoDateTime,
      })
      .nullable(),
  }),
  personal_rhythm: z.object({
    has_activity: z.boolean(),
    days: z.array(z.object({ date: isoDate, focus_minutes: z.number().int() })),
  }),
});

export type Dashboard = z.infer<typeof dashboardSchema>;

export async function getDashboard(timezone: string): Promise<Dashboard> {
  const payload = await apiFetch(
    `/api/v1/dashboard?timezone=${encodeURIComponent(timezone)}`,
  );

  return dashboardSchema.parse(envelopeData(payload));
}
