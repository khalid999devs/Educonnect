import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/**
 * Exact runtime shape of GET /api/v1/progress (the authoritative progress read
 * model; the dashboard delegates to the same server query).
 *
 * PRODUCT INVARIANT: this schema mirrors the OpenAPI `Progress` schema, which
 * sets `additionalProperties: false` precisely so a streak, a consecutive-day
 * counter, a badge, a percentile, or a period-over-period delta cannot be
 * added by accident. Do not add one here either. If the window is empty, the
 * payload reports zeros and `has_activity: false`, and the UI says so plainly.
 */

const isoDate = z.string();

export const progressWindowSchema = z.enum(["week", "month", "term"]);

export type ProgressWindow = z.infer<typeof progressWindowSchema>;

export const progressTotalsSchema = z.object({
  tasks_completed: z.number().int(),
  tasks_due: z.number().int(),
  focus_minutes: z.number().int(),
  resources_added: z.number().int(),
  intake_items_processed: z.number().int(),
  knowledge_items_added: z.number().int(),
  notes_written: z.number().int(),
  template_copies_created: z.number().int(),
  research_sources_reviewed: z.number().int(),
});

export type ProgressTotals = z.infer<typeof progressTotalsSchema>;

export const progressDaySchema = z.object({
  date: isoDate,
  tasks_completed: z.number().int(),
  focus_minutes: z.number().int(),
  resources_added: z.number().int(),
  notes_written: z.number().int(),
});

export type ProgressDay = z.infer<typeof progressDaySchema>;

export const activityRhythmDaySchema = z.object({
  date: isoDate,
  was_active: z.boolean(),
  signals: z.object({
    tasks: z.number().int(),
    focus_minutes: z.number().int(),
    resources: z.number().int(),
    notes: z.number().int(),
  }),
});

export type ActivityRhythmDay = z.infer<typeof activityRhythmDaySchema>;

export const progressSchema = z.object({
  timeframe: z.object({
    timezone: z.string(),
    window: progressWindowSchema,
    starts_on: isoDate,
    ends_on: isoDate,
    term_label: z.string().nullable(),
  }),
  has_activity: z.boolean(),
  summary: z.string(),
  totals: progressTotalsSchema,
  daily: z.array(progressDaySchema),
  activity_rhythm: z.object({
    has_activity: z.boolean(),
    days: z.array(activityRhythmDaySchema),
  }),
  next_action: z
    .object({
      kind: z.enum(["task", "intake_review"]),
      id: z.string(),
      title: z.string(),
      due_at: z.string().nullable(),
    })
    .nullable(),
});

export type Progress = z.infer<typeof progressSchema>;

export type GetProgressParams = {
  /** Browser-derived; there is no persisted timezone (build brief 5.4). */
  timezone: string;
  window?: ProgressWindow;
};

export async function getProgress({
  timezone,
  window,
}: GetProgressParams): Promise<Progress> {
  const payload = await apiFetch(
    `/api/v1/progress${toQueryString({ timezone, window })}`,
  );

  return progressSchema.parse(envelopeData(payload));
}
