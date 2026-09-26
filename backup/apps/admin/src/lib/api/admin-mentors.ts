import { apiFetch, toQueryString } from "./http";
import { parsePage, parseResource, type Page } from "./pagination";
import { z } from "zod";

export const VERIFICATION_STATES = ["unverified", "verified"] as const;

export const mentorProfileSchema = z.object({
  id: z.string(),
  name: z.string(),
  headline: z.string(),
  bio: z.string(),
  expertise: z.array(z.string()),
  availability_note: z.string().nullable(),
  verification_state: z.enum(VERIFICATION_STATES),
  is_accepting_requests: z.boolean(),
  version: z.number(),
  created_at: z.string(),
});

export type MentorProfile = z.infer<typeof mentorProfileSchema>;

export type MentorFilters = {
  search?: string;
  verificationState?: string;
  cursor?: string;
};

export async function listMentors(
  filters: MentorFilters,
): Promise<Page<MentorProfile>> {
  const query = toQueryString({
    search: filters.search || undefined,
    verification_state: filters.verificationState || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/mentors${query}`),
    mentorProfileSchema,
  );
}

export async function setMentorVerification(input: {
  id: string;
  verificationState: (typeof VERIFICATION_STATES)[number];
  expectedVersion: number;
  reason: string;
}): Promise<MentorProfile> {
  return parseResource(
    await apiFetch(`/api/v1/admin/mentors/${input.id}/verification`, {
      method: "PATCH",
      body: {
        verification_state: input.verificationState,
        expected_version: input.expectedVersion,
        reason: input.reason,
      },
    }),
    mentorProfileSchema,
  );
}
