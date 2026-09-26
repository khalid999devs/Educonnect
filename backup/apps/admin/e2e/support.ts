import type { Page } from "@playwright/test";

export const API = "http://localhost:8000";
const ORIGIN = "http://localhost:3101";

export function adminUser(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JADMIN0000000000000000AA",
    name: "Ada Admin",
    email: "admin@example.com",
    email_verified: true,
    primary_role: "admin",
    ...overrides,
  };
}

export function adminSession(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    user: adminUser(),
    authorization: {
      roles: ["admin"],
      capabilities: ["admin.access"],
    },
    ...overrides,
  };
}

export function envelope(data: unknown) {
  return { data, meta: { request_id: "e2e-request" } };
}

export function validationError(field: string, message: string) {
  return {
    error: {
      code: "VALIDATION_FAILED",
      message: "The given data was invalid.",
      details: { fields: { [field]: [message] } },
      request_id: "e2e-request",
    },
  };
}

export async function mockCsrf(page: Page) {
  await page.route(`${API}/sanctum/csrf-cookie`, async (route) => {
    await route.fulfill({
      status: 204,
      headers: {
        "Set-Cookie": "XSRF-TOKEN=e2e-token; Path=/",
        "Access-Control-Allow-Origin": ORIGIN,
        "Access-Control-Allow-Credentials": "true",
      },
    });
  });
}

export async function mockJson(
  page: Page,
  url: string | RegExp,
  body: unknown,
  status = 200,
) {
  await page.route(url, async (route) => {
    await route.fulfill({
      status,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": ORIGIN,
        "Access-Control-Allow-Credentials": "true",
        "Access-Control-Allow-Headers": "content-type,x-xsrf-token,accept",
        "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
      },
      body: JSON.stringify(body),
    });
  });
}

/** An admin session with an explicit role + capability set for gating tests. */
export function sessionWith(roles: string[], capabilities: string[]) {
  return {
    user: adminUser({ primary_role: roles[0] ?? "admin" }),
    authorization: { roles, capabilities },
  };
}

export const FULL_ADMIN_CAPS = [
  "admin.access",
  "authorization.roles-view",
  "authorization.roles-assign",
  "users.suspend",
  "mentors.curate",
  "content.curate",
  "moderation.global",
  "audit.view-all",
];

export function adminCommunityRow(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    id: "01JCOMM00000000000000000AA",
    slug: "study-skills",
    name: "Study Skills",
    summary: "Share revision techniques.",
    description: null,
    topic: "Productivity",
    visibility: "published",
    is_seeded: true,
    member_count: 12,
    version: 1,
    created_at: "2026-07-01T09:00:00Z",
    ...overrides,
  };
}

export function operationalOverview() {
  const counts = { draft: 1, in_review: 0, published: 2, archived: 0 };
  return {
    users: {
      total: 5,
      active: 4,
      suspended: 1,
      by_role: { admin: 1, student: 4 },
    },
    content: {
      tools: counts,
      prompts: counts,
      workflows: counts,
      templates: counts,
    },
    community: {
      communities: 2,
      memberships: 20,
      reports: { open: 3, reviewing: 1, actioned: 0, dismissed: 0 },
    },
    mentors: { verified: 2, unverified: 1 },
    audit_event_count: 42,
  };
}

export function operationalTelemetry() {
  return {
    window_hours: 24,
    generated_at: "2026-07-19T12:00:00Z",
    ai: {
      total: 12,
      by_outcome: { success: 10, failure: 1, fallback: 1, degraded: 0 },
      fallback_rate: 0.0833,
      failure_rate: 0.0833,
      latency_ms: { p50: 620, p95: 1400, p99: 1900 },
      by_feature: { "intake.classification": 9, copilot: 3 },
    },
    jobs: {
      total: 8,
      by_outcome: { success: 7, failure: 1, fallback: 0, degraded: 0 },
      failure_rate: 0.125,
      by_job: { "intake.process": 5, "intake.classify": 3 },
    },
    errors: {
      total: 1,
      by_code: { internal_error: 1 },
    },
    http: {
      request_count: 340,
      error_count: 2,
      error_rate: 0.0059,
      latency_ms: { p50: 50, p95: 250, p99: 500 },
      window_seconds: 3600,
    },
  };
}

export function adminToolRow(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JTOOL00000000000000000AA",
    name: "Concept Mapper",
    category: { slug: "study-planning", name: "Study planning" },
    purpose: "Map a dense reading.",
    selection_reason: "Keeps sources visible.",
    use_cases: ["Revision"],
    usage_guidance: "Paste your notes.",
    limitations: "Cannot judge quality.",
    cost_note: "Free tier.",
    privacy_note: "No personal data.",
    url: "https://tools.example.edu/concept-mapper",
    provenance: "Reviewed against provider docs.",
    state: "draft",
    last_reviewed_at: null,
    published_at: null,
    archived_at: null,
    version: 1,
    created_at: "2026-07-01T09:00:00Z",
    updated_at: "2026-07-10T09:00:00Z",
    ...overrides,
  };
}

export const MODERATOR_CAPS = [
  "admin.access",
  "moderation.scoped",
  "audit.view-scoped",
];

export function collection(items: unknown[], nextCursor: string | null = null) {
  return {
    data: items,
    meta: {
      pagination: {
        next_cursor: nextCursor,
        previous_cursor: null,
        per_page: 20,
      },
      request_id: "e2e-request",
    },
    links: { next: null, previous: null },
  };
}

export function adminUserRow(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JUSER00000000000000000AA",
    name: "Priya Patel",
    email: "priya@example.com",
    email_verified: true,
    status: "active",
    suspended_at: null,
    roles: ["student"],
    last_login_at: "2026-07-18T09:00:00Z",
    created_at: "2026-07-01T09:00:00Z",
    ...overrides,
  };
}

export function reportRow(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JREPORT000000000000000AA",
    community: { id: "01JCOMM00000000000000000AA", name: "Study Skills" },
    subject: {
      type: "post",
      id: "01JPOST0000000000000000AAA",
      excerpt: "Looking for study buddies.",
    },
    reason: "spam",
    detail: null,
    status: "open",
    resolution_note: null,
    version: 1,
    handled_at: null,
    created_at: "2026-07-18T09:00:00Z",
    ...overrides,
  };
}

export function auditRow(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JAUDIT0000000000000000AA",
    action: "users.account-suspended",
    actor: {
      type: "user",
      id: "01JADMIN0000000000000000AA",
      name: "Ada Admin",
    },
    subject: { type: "user", id: "01JUSER00000000000000000AA" },
    reason: "Repeated policy violations.",
    before_state: { account_status: ["active"] },
    after_state: { account_status: ["suspended"] },
    request_id: "req-00000000000000000000000000000000",
    created_at: "2026-07-18T09:05:00Z",
    ...overrides,
  };
}
