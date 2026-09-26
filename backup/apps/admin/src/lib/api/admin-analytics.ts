import { apiFetch, envelopeData } from "./http";
import { z } from "zod";

const stateCounts = z.object({
  draft: z.number(),
  in_review: z.number(),
  published: z.number(),
  archived: z.number(),
});

export const operationalOverviewSchema = z.object({
  users: z.object({
    total: z.number(),
    active: z.number(),
    suspended: z.number(),
    by_role: z.record(z.string(), z.number()),
  }),
  content: z.object({
    tools: stateCounts,
    prompts: stateCounts,
    workflows: stateCounts,
    templates: stateCounts,
  }),
  community: z.object({
    communities: z.number(),
    memberships: z.number(),
    reports: z.object({
      open: z.number(),
      reviewing: z.number(),
      actioned: z.number(),
      dismissed: z.number(),
    }),
  }),
  mentors: z.object({ verified: z.number(), unverified: z.number() }),
  audit_event_count: z.number(),
});

export type OperationalOverview = z.infer<typeof operationalOverviewSchema>;

export async function getOperationalOverview(): Promise<OperationalOverview> {
  return operationalOverviewSchema.parse(
    envelopeData(await apiFetch("/api/v1/admin/analytics")),
  );
}

const outcomeCounts = z.object({
  success: z.number(),
  failure: z.number(),
  fallback: z.number(),
  degraded: z.number(),
});

const latency = z.object({
  p50: z.number().nullable(),
  p95: z.number().nullable(),
  p99: z.number().nullable(),
});

export const operationalTelemetrySchema = z.object({
  window_hours: z.number(),
  generated_at: z.string(),
  ai: z.object({
    total: z.number(),
    by_outcome: outcomeCounts,
    fallback_rate: z.number(),
    failure_rate: z.number(),
    latency_ms: latency,
    by_feature: z.record(z.string(), z.number()),
  }),
  jobs: z.object({
    total: z.number(),
    by_outcome: outcomeCounts,
    failure_rate: z.number(),
    by_job: z.record(z.string(), z.number()),
  }),
  errors: z.object({
    total: z.number(),
    by_code: z.record(z.string(), z.number()),
  }),
  http: z.object({
    request_count: z.number(),
    error_count: z.number(),
    error_rate: z.number(),
    latency_ms: latency,
    window_seconds: z.number(),
  }),
});

export type OperationalTelemetry = z.infer<typeof operationalTelemetrySchema>;

export async function getOperationalTelemetry(): Promise<OperationalTelemetry> {
  return operationalTelemetrySchema.parse(
    envelopeData(await apiFetch("/api/v1/admin/telemetry")),
  );
}

export async function seedDemoContent(
  reason: string,
): Promise<{ tools: number; prompts: number; workflows: number }> {
  const data = envelopeData(
    await apiFetch("/api/v1/admin/demo-data", {
      method: "POST",
      body: { reason },
    }),
  );

  return z
    .object({
      catalog: z.object({
        tools: z.number(),
        prompts: z.number(),
        workflows: z.number(),
      }),
    })
    .parse(data).catalog;
}
