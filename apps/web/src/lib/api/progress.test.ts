import { describe, expect, it } from "vitest";

import { progressSchema } from "./progress";

export const PROGRESS_FIXTURE = {
  timeframe: {
    timezone: "Asia/Dhaka",
    window: "week",
    starts_on: "2026-07-20",
    ends_on: "2026-07-27",
    term_label: null,
  },
  has_activity: true,
  summary: "3 tasks completed, 90 focus minutes logged this week.",
  totals: {
    tasks_completed: 3,
    tasks_due: 5,
    focus_minutes: 90,
    resources_added: 2,
    intake_items_processed: 1,
    knowledge_items_added: 4,
    notes_written: 6,
    template_copies_created: 1,
    research_sources_reviewed: 2,
  },
  daily: [
    {
      date: "2026-07-20",
      tasks_completed: 1,
      focus_minutes: 25,
      resources_added: 1,
      notes_written: 2,
    },
    {
      date: "2026-07-21",
      tasks_completed: 2,
      focus_minutes: 65,
      resources_added: 1,
      notes_written: 4,
    },
  ],
  activity_rhythm: {
    has_activity: true,
    days: [
      {
        date: "2026-07-20",
        was_active: true,
        signals: { tasks: 1, focus_minutes: 25, resources: 1, notes: 2 },
      },
      {
        date: "2026-07-21",
        was_active: false,
        signals: { tasks: 0, focus_minutes: 0, resources: 0, notes: 0 },
      },
    ],
  },
  next_action: {
    kind: "task",
    id: "01JTASK0000000000000000000",
    title: "Problem set 2",
    due_at: "2026-07-22T18:00:00Z",
  },
} as const;

describe("progressSchema", () => {
  it("parses the documented ProgressResource payload", () => {
    const parsed = progressSchema.parse(PROGRESS_FIXTURE);

    expect(parsed.timeframe.window).toBe("week");
    expect(parsed.totals.tasks_completed).toBe(3);
    expect(parsed.activity_rhythm.days).toHaveLength(2);
    expect(parsed.next_action?.kind).toBe("task");
  });

  it("accepts an empty window with a null next action", () => {
    const parsed = progressSchema.parse({
      ...PROGRESS_FIXTURE,
      has_activity: false,
      next_action: null,
    });

    expect(parsed.has_activity).toBe(false);
    expect(parsed.next_action).toBeNull();
  });

  it("rejects an unknown window value", () => {
    expect(() =>
      progressSchema.parse({
        ...PROGRESS_FIXTURE,
        timeframe: { ...PROGRESS_FIXTURE.timeframe, window: "year" },
      }),
    ).toThrow();
  });

  it("rejects a non-integer count", () => {
    expect(() =>
      progressSchema.parse({
        ...PROGRESS_FIXTURE,
        totals: { ...PROGRESS_FIXTURE.totals, tasks_completed: 1.5 },
      }),
    ).toThrow();
  });

  it("rejects a missing activity rhythm", () => {
    const { activity_rhythm: _omitted, ...rest } = PROGRESS_FIXTURE;

    expect(() => progressSchema.parse(rest)).toThrow();
  });
});
