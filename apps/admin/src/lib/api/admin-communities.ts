import { apiFetch, toQueryString } from "./http";
import { parsePage, parseResource, type Page } from "./pagination";
import { z } from "zod";

export const COMMUNITY_VISIBILITIES = ["published", "archived"] as const;

export const adminCommunitySchema = z.object({
  id: z.string(),
  slug: z.string(),
  name: z.string(),
  summary: z.string(),
  description: z.string().nullable(),
  topic: z.string().nullable(),
  visibility: z.enum(COMMUNITY_VISIBILITIES),
  is_seeded: z.boolean(),
  member_count: z.number(),
  version: z.number(),
  created_at: z.string(),
});

export type AdminCommunity = z.infer<typeof adminCommunitySchema>;

export type CommunityContent = {
  name: string;
  summary: string;
  description: string | null;
  topic: string | null;
};

export async function listCommunities(filters: {
  visibility?: string;
  cursor?: string;
}): Promise<Page<AdminCommunity>> {
  const query = toQueryString({
    visibility: filters.visibility || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/communities${query}`),
    adminCommunitySchema,
  );
}

function body(content: CommunityContent): Record<string, unknown> {
  return {
    name: content.name,
    summary: content.summary,
    description: content.description || undefined,
    topic: content.topic || undefined,
  };
}

export async function createCommunity(
  content: CommunityContent,
): Promise<AdminCommunity> {
  return parseResource(
    await apiFetch("/api/v1/admin/communities", {
      method: "POST",
      body: body(content),
    }),
    adminCommunitySchema,
  );
}

export async function updateCommunity(
  id: string,
  content: CommunityContent,
  expectedVersion: number,
): Promise<AdminCommunity> {
  return parseResource(
    await apiFetch(`/api/v1/admin/communities/${id}`, {
      method: "PUT",
      body: { ...body(content), expected_version: expectedVersion },
    }),
    adminCommunitySchema,
  );
}

export async function setCommunityVisibility(input: {
  id: string;
  visibility: (typeof COMMUNITY_VISIBILITIES)[number];
  expectedVersion: number;
  reason: string;
}): Promise<AdminCommunity> {
  return parseResource(
    await apiFetch(`/api/v1/admin/communities/${input.id}/visibility`, {
      method: "PATCH",
      body: {
        visibility: input.visibility,
        expected_version: input.expectedVersion,
        reason: input.reason,
      },
    }),
    adminCommunitySchema,
  );
}
