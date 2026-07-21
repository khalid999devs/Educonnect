import { z } from "zod";

import { ApiError, apiFetch, envelopeData, toQueryString } from "./http";

/** Runtime shapes of the Mentor contract (openapi.yaml): a mentor directory with
 * a truthful verification/availability note (never a rating or session count) and
 * a request-help flow. No booking, payments, or messaging exist in the MVP. */

const isoDateTime = z.string();

export const verificationStateSchema = z.enum(["unverified", "verified"]);
export type VerificationState = z.infer<typeof verificationStateSchema>;

export const mentorRequestStatusSchema = z.enum([
  "open",
  "accepted",
  "declined",
  "withdrawn",
  "completed",
]);
export type MentorRequestStatus = z.infer<typeof mentorRequestStatusSchema>;

export const mentorProfileSchema = z.object({
  id: z.string(),
  name: z.string(),
  headline: z.string(),
  bio: z.string(),
  expertise: z.array(z.string()),
  availability_note: z.string().nullable(),
  verification_state: verificationStateSchema,
  is_accepting_requests: z.boolean(),
  version: z.number().int().min(1),
  created_at: isoDateTime,
});
export type MentorProfile = z.infer<typeof mentorProfileSchema>;

export const mentorRequestSchema = z.object({
  id: z.string(),
  subject: z.string(),
  message: z.string(),
  status: mentorRequestStatusSchema,
  response_note: z.string().nullable(),
  mentor: z
    .object({ id: z.string(), name: z.string(), headline: z.string() })
    .optional(),
  requester: z.object({ name: z.string() }).optional(),
  version: z.number().int().min(1),
  created_at: isoDateTime,
  responded_at: isoDateTime.nullable(),
});
export type MentorRequest = z.infer<typeof mentorRequestSchema>;

const collectionMeta = z.object({
  summary: z.record(z.string(), z.number()).optional(),
  pagination: z.object({
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    per_page: z.number().int(),
  }),
});

const mentorCollectionSchema = z.object({
  data: z.array(mentorProfileSchema),
  meta: collectionMeta,
});
export type MentorPage = z.infer<typeof mentorCollectionSchema>;

const requestCollectionSchema = z.object({
  data: z.array(mentorRequestSchema),
  meta: collectionMeta,
});
export type MentorRequestPage = z.infer<typeof requestCollectionSchema>;

type Cursor = { perPage?: number; cursor?: string };

export async function listMentors(
  params: { search?: string; expertise?: string } & Cursor = {},
): Promise<MentorPage> {
  const query = toQueryString({
    search: params.search,
    expertise: params.expertise,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return mentorCollectionSchema.parse(
    await apiFetch(`/api/v1/mentors${query}`),
  );
}

export async function getMentor(id: string): Promise<MentorProfile> {
  return mentorProfileSchema.parse(
    envelopeData(await apiFetch(`/api/v1/mentors/${id}`)),
  );
}

/** Returns the caller's own mentor profile, or null when they have none yet. */
export async function getOwnMentorProfile(): Promise<MentorProfile | null> {
  try {
    return mentorProfileSchema.parse(
      envelopeData(await apiFetch(`/api/v1/mentor-profile`)),
    );
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return null;
    }

    throw error;
  }
}

export type MentorProfileInput = {
  headline: string;
  bio: string;
  expertise: string[];
  availability_note?: string | null;
  is_accepting_requests?: boolean;
};

export async function createMentorProfile(
  input: MentorProfileInput,
): Promise<MentorProfile> {
  return mentorProfileSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/mentor-profile`, { method: "POST", body: input }),
    ),
  );
}

export async function updateMentorProfile(
  input: MentorProfileInput & { expected_version: number },
): Promise<MentorProfile> {
  return mentorProfileSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/mentor-profile`, {
        method: "PATCH",
        body: input,
      }),
    ),
  );
}

export async function createMentorRequest(
  mentorId: string,
  input: {
    subject: string;
    message: string;
    context_course_id?: string | null;
  },
): Promise<MentorRequest> {
  return mentorRequestSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/mentors/${mentorId}/requests`, {
        method: "POST",
        body: input,
      }),
    ),
  );
}

type RequestFilters = { status?: MentorRequestStatus[] } & Cursor;

function requestQuery(params: RequestFilters): string {
  return toQueryString({
    status: params.status?.length ? params.status.join(",") : undefined,
    per_page: params.perPage,
    cursor: params.cursor,
  });
}

export async function listSentRequests(
  params: RequestFilters = {},
): Promise<MentorRequestPage> {
  return requestCollectionSchema.parse(
    await apiFetch(`/api/v1/mentor-requests${requestQuery(params)}`),
  );
}

export async function listIncomingRequests(
  params: RequestFilters = {},
): Promise<MentorRequestPage> {
  return requestCollectionSchema.parse(
    await apiFetch(`/api/v1/mentor-requests/incoming${requestQuery(params)}`),
  );
}

/** The statuses that count as "this student has a mentor". A completed
 * mentorship still counts: finishing an engagement must not take the student's
 * mentorship history away from them. */
export const CONNECTED_MENTOR_STATUSES: MentorRequestStatus[] = [
  "accepted",
  "completed",
];

/** Nav gate: does the caller hold a mentor connection? Asks for a single row and
 * reads the filtered total from the collection meta, so it never pulls a page of
 * requests just to answer a boolean. */
export async function hasAcceptedMentor(): Promise<boolean> {
  const page = await listSentRequests({
    status: CONNECTED_MENTOR_STATUSES,
    perPage: 1,
  });

  const total = page.meta.summary?.total;

  return typeof total === "number" ? total > 0 : page.data.length > 0;
}

export async function transitionMentorRequest(
  id: string,
  input: {
    action: "withdraw" | "accept" | "decline" | "complete";
    response_note?: string | null;
    expected_version: number;
  },
): Promise<MentorRequest> {
  return mentorRequestSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/mentor-requests/${id}`, {
        method: "PATCH",
        body: input,
      }),
    ),
  );
}
