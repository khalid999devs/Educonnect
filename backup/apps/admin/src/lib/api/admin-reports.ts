import { apiFetch, toQueryString } from "./http";
import { parsePage, parseResource, type Page } from "./pagination";
import { z } from "zod";

export const REPORT_STATUSES = [
  "open",
  "reviewing",
  "actioned",
  "dismissed",
] as const;

export const contentReportSchema = z.object({
  id: z.string(),
  community: z.object({ id: z.string(), name: z.string() }).optional(),
  subject: z.object({
    type: z.enum(["post", "comment"]),
    id: z.string().nullable(),
    excerpt: z.string().nullable(),
  }),
  reason: z.string(),
  detail: z.string().nullable(),
  status: z.enum(REPORT_STATUSES),
  resolution_note: z.string().nullable(),
  version: z.number(),
  handled_at: z.string().nullable(),
  created_at: z.string(),
});

export type ContentReport = z.infer<typeof contentReportSchema>;

export async function listReports(filters: {
  status?: string;
  cursor?: string;
}): Promise<Page<ContentReport>> {
  const query = toQueryString({
    status: filters.status || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/reports${query}`),
    contentReportSchema,
  );
}

export async function resolveReport(input: {
  id: string;
  resolution: "actioned" | "dismissed";
  hideContent: boolean;
  note: string | null;
  expectedVersion: number;
  reason: string;
}): Promise<ContentReport> {
  return parseResource(
    await apiFetch(`/api/v1/admin/reports/${input.id}/resolution`, {
      method: "PATCH",
      body: {
        resolution: input.resolution,
        hide_content: input.hideContent,
        note: input.note ?? undefined,
        expected_version: input.expectedVersion,
        reason: input.reason,
      },
    }),
    contentReportSchema,
  );
}
