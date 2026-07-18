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
