import type { Page } from "@playwright/test";

export const API = "http://localhost:8000";

export function user(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JZXUSER",
    name: "Sam Student",
    email: "sam@example.com",
    email_verified: true,
    primary_role: "student",
    ...overrides,
  };
}

export function onboarding(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    version: 1,
    status: "not_started",
    current_step: "institution",
    can_complete: false,
    completed_at: null,
    steps: {
      institution: "pending",
      program: "pending",
      study_stage: "pending",
      courses: "pending",
      goals: "pending",
      first_source: "pending",
    },
    profile: {
      institution_name: null,
      institution_country_code: null,
      department: null,
      degree: null,
      major: null,
      year_label: null,
      term_label: null,
    },
    course_drafts: [],
    goals: [],
    problems: [],
    first_source_draft: null,
    starter_context: null,
    ...overrides,
  };
}

export function envelope(data: unknown) {
  return { data, meta: { request_id: "e2e-request" } };
}

export async function mockCsrf(page: Page) {
  await page.route(`${API}/sanctum/csrf-cookie`, async (route) => {
    await route.fulfill({
      status: 204,
      headers: {
        "Set-Cookie": "XSRF-TOKEN=e2e-token; Path=/",
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
    });
  });
}

export async function mockJson(
  page: Page,
  url: string,
  body: unknown,
  status = 200,
) {
  await page.route(url, async (route) => {
    await route.fulfill({
      status,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
        "Access-Control-Allow-Headers": "content-type,x-xsrf-token,accept",
        "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
      },
      body: JSON.stringify(body),
    });
  });
}

export function dashboard(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    timeframe: {
      timezone: "UTC",
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
      term: { id: "01JTERM0000000000000000000", label: "Fall 2026" },
      active_course_count: 2,
    },
    quick_intake: { active_item: null, awaiting_review_count: 0 },
    whats_next: {
      tasks: [
        {
          id: "01jtask000000000000000000t",
          title: "Problem set 2",
          status: "pending",
          due_at: "2026-07-18T18:00:00Z",
          overdue: false,
          course: {
            id: "01jcourse00000000000000000",
            title: "Data Structures",
          },
        },
      ],
      overdue_count: 0,
      upcoming_count: 1,
    },
    tools: [],
    today: { due_task_count: 0, due_tasks: [], next_focus_session: null },
    second_brain: { total_item_count: 0, recent_items: [] },
    progress: {
      timeframe: {
        timezone: "UTC",
        starts_on: "2026-07-13",
        ends_on: "2026-07-19",
      },
      completed_task_count: 0,
      due_task_count: 1,
      focus_minutes: 0,
      daily_completed: [
        { date: "2026-07-13", completed: 0 },
        { date: "2026-07-14", completed: 0 },
      ],
      summary: "You completed 0 of 1 tasks due this week.",
      next_action: {
        kind: "task",
        id: "01jtask000000000000000000t",
        title: "Problem set 2",
        due_at: "2026-07-18T18:00:00Z",
      },
    },
    personal_rhythm: { has_activity: false, days: [] },
    ...overrides,
  };
}
