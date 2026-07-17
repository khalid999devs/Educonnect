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
