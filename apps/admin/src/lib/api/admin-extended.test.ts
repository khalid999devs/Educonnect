import { describe, expect, it } from "vitest";

import { adminCommunitySchema } from "./admin-communities";
import { adminTemplateSchema } from "./admin-content";
import {
  operationalOverviewSchema,
  operationalTelemetrySchema,
} from "./admin-analytics";

describe("adminTemplateSchema", () => {
  it("parses a template with a latest version", () => {
    const template = adminTemplateSchema.parse({
      id: "01JTMPL00000000000000000AA",
      title: "Assignment Structure",
      category: { slug: "academic-writing", name: "Academic writing" },
      summary: "An outline.",
      badge: "approved_free",
      integrity_note: "A scaffold.",
      provenance: "Reviewed.",
      latest_version: { number: 1, format: "markdown", body: "# Title" },
      state: "draft",
      last_reviewed_at: null,
      published_at: null,
      archived_at: null,
      version: 1,
      created_at: "2026-07-01T09:00:00Z",
      updated_at: "2026-07-01T09:00:00Z",
    });

    expect(template.latest_version?.number).toBe(1);
    expect(template.badge).toBe("approved_free");
  });
});

describe("adminCommunitySchema", () => {
  it("parses a managed community", () => {
    const community = adminCommunitySchema.parse({
      id: "01JCOMM00000000000000000AA",
      slug: "thesis-writers",
      name: "Thesis Writers",
      summary: "Support.",
      description: null,
      topic: "Academic writing",
      visibility: "published",
      is_seeded: false,
      member_count: 4,
      version: 1,
      created_at: "2026-07-01T09:00:00Z",
    });

    expect(community.visibility).toBe("published");
    expect(community.member_count).toBe(4);
  });
});

describe("operationalOverviewSchema", () => {
  it("parses the aggregate overview", () => {
    const overview = operationalOverviewSchema.parse({
      users: { total: 3, active: 2, suspended: 1, by_role: { admin: 1 } },
      content: {
        tools: { draft: 1, in_review: 0, published: 2, archived: 0 },
        prompts: { draft: 0, in_review: 0, published: 1, archived: 0 },
        workflows: { draft: 0, in_review: 0, published: 1, archived: 0 },
        templates: { draft: 1, in_review: 0, published: 0, archived: 0 },
      },
      community: {
        communities: 2,
        memberships: 5,
        reports: { open: 1, reviewing: 0, actioned: 0, dismissed: 0 },
      },
      mentors: { verified: 1, unverified: 2 },
      audit_event_count: 7,
    });

    expect(overview.users.suspended).toBe(1);
    expect(overview.content.tools.published).toBe(2);
  });
});

describe("operationalTelemetrySchema", () => {
  it("parses telemetry with null latencies and empty maps", () => {
    const telemetry = operationalTelemetrySchema.parse({
      window_hours: 24,
      generated_at: "2026-07-19T12:00:00Z",
      ai: {
        total: 0,
        by_outcome: { success: 0, failure: 0, fallback: 0, degraded: 0 },
        fallback_rate: 0,
        failure_rate: 0,
        latency_ms: { p50: null, p95: null, p99: null },
        by_feature: {},
      },
      jobs: {
        total: 0,
        by_outcome: { success: 0, failure: 0, fallback: 0, degraded: 0 },
        failure_rate: 0,
        by_job: {},
      },
      errors: { total: 0, by_code: {} },
      http: {
        request_count: 0,
        error_count: 0,
        error_rate: 0,
        latency_ms: { p50: null, p95: null, p99: null },
        window_seconds: 3600,
      },
    });

    expect(telemetry.ai.latency_ms.p95).toBeNull();
    expect(telemetry.http.request_count).toBe(0);
  });
});
