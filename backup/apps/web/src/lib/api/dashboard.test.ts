import { describe, expect, it } from "vitest";

import { dashboardSchema } from "./dashboard";

export const DASHBOARD_FIXTURE = {
  timeframe: {
    timezone: "Asia/Dhaka",
    today: "2026-07-17",
    week_starts_on: "2026-07-13",
    week_ends_on: "2026-07-19",
  },
  cover: {
    name: "Sam Student",
    institution: "University of Dhaka",
    degree: "B.Sc.",
    major: "CSE",
    study_stage: "Year 2",
    term: { id: "01JTERM", label: "Fall 2026" },
    active_course_count: 2,
  },
  quick_intake: {
    active_item: {
      id: "01JITEM",
      state: "awaiting_review",
      failure_code: null,
      submitted_at: "2026-07-16T10:00:00Z",
    },
    awaiting_review_count: 1,
  },
  whats_next: {
    tasks: [
      {
        id: "01JTASK",
        title: "Problem set 2",
        status: "pending",
        due_at: "2026-07-18T18:00:00Z",
        overdue: false,
        course: { id: "01JCOURSE", title: "Data Structures" },
      },
    ],
    overdue_count: 0,
    upcoming_count: 1,
  },
  tools: [
    { id: "01JTOOL", name: "Reviewed tool", category: "Writing", saved: false },
  ],
  today: {
    due_task_count: 1,
    due_tasks: [
      { id: "01JTASK", title: "Problem set 2", due_at: "2026-07-17T18:00:00Z" },
    ],
    next_focus_session: {
      id: "01JFOCUS",
      starts_at: "2026-07-17T14:00:00Z",
      ends_at: "2026-07-17T15:00:00Z",
      in_progress: false,
      task_title: "Deep work",
      course_title: "Data Structures",
    },
  },
  second_brain: {
    total_item_count: 3,
    recent_items: [
      {
        id: "01JKNOW",
        title: "Big-O summary",
        source_type: "none",
        updated_at: "2026-07-16T09:00:00Z",
      },
    ],
  },
  progress: {
    timeframe: {
      timezone: "Asia/Dhaka",
      starts_on: "2026-07-13",
      ends_on: "2026-07-19",
    },
    completed_task_count: 1,
    due_task_count: 3,
    focus_minutes: 60,
    daily_completed: [
      { date: "2026-07-13", completed: 1 },
      { date: "2026-07-14", completed: 0 },
    ],
    summary: "You completed 1 of 3 tasks due this week.",
    next_action: {
      kind: "task",
      id: "01JTASK",
      title: "Problem set 2",
      due_at: "2026-07-18T18:00:00Z",
    },
  },
  personal_rhythm: {
    has_activity: true,
    days: [{ date: "2026-07-17", focus_minutes: 60 }],
  },
};

describe("dashboard schema", () => {
  it("parses a populated aggregate", () => {
    const dashboard = dashboardSchema.parse(DASHBOARD_FIXTURE);

    expect(dashboard.whats_next.tasks[0]?.course?.title).toBe(
      "Data Structures",
    );
    expect(dashboard.progress.next_action?.kind).toBe("task");
  });

  it("parses an honest empty aggregate", () => {
    const empty = dashboardSchema.parse({
      ...DASHBOARD_FIXTURE,
      cover: {
        name: "New Student",
        institution: null,
        degree: null,
        major: null,
        study_stage: null,
        term: null,
        active_course_count: 0,
      },
      quick_intake: { active_item: null, awaiting_review_count: 0 },
      whats_next: { tasks: [], overdue_count: 0, upcoming_count: 0 },
      tools: [],
      today: { due_task_count: 0, due_tasks: [], next_focus_session: null },
      second_brain: { total_item_count: 0, recent_items: [] },
      progress: {
        ...DASHBOARD_FIXTURE.progress,
        completed_task_count: 0,
        due_task_count: 0,
        focus_minutes: 0,
        daily_completed: [],
        summary: "No planner activity recorded this week yet.",
        next_action: null,
      },
      personal_rhythm: { has_activity: false, days: [] },
    });

    expect(empty.progress.next_action).toBeNull();
  });

  it("rejects an unknown task status", () => {
    expect(() =>
      dashboardSchema.parse({
        ...DASHBOARD_FIXTURE,
        whats_next: {
          ...DASHBOARD_FIXTURE.whats_next,
          tasks: [
            {
              ...DASHBOARD_FIXTURE.whats_next.tasks[0],
              status: "completed",
            },
          ],
        },
      }),
    ).toThrow();
  });
});
