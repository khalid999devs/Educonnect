import { apiFetch, toQueryString } from "./http";
import { parsePage, type Page } from "./pagination";
import { z } from "zod";

export const auditEventSchema = z.object({
  id: z.string(),
  action: z.string(),
  actor: z.object({
    type: z.string(),
    id: z.string().nullable(),
    name: z.string().nullable().optional(),
  }),
  subject: z.object({ type: z.string(), id: z.string() }),
  reason: z.string(),
  before_state: z.record(z.string(), z.unknown()),
  after_state: z.record(z.string(), z.unknown()),
  request_id: z.string(),
  created_at: z.string(),
});

export type AuditEvent = z.infer<typeof auditEventSchema>;

export async function listAuditEvents(filters: {
  action?: string;
  actorId?: string;
  subjectType?: string;
  cursor?: string;
}): Promise<Page<AuditEvent>> {
  const query = toQueryString({
    action: filters.action || undefined,
    actor_id: filters.actorId || undefined,
    subject_type: filters.subjectType || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/audit-events${query}`),
    auditEventSchema,
  );
}
