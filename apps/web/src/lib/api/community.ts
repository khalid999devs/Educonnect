import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/** Runtime shapes of the Community contract (openapi.yaml): curated communities,
 * a membership-scoped feed of posts and comments, and an abuse-report ladder that
 * feeds a scoped moderation queue. All source-derived text renders as escaped
 * React text — there is no rich-text/HTML rendering on this path. */

const isoDateTime = z.string();

export const moderationStateSchema = z.enum([
  "visible",
  "hidden_by_moderator",
  "removed_by_author",
]);
export type ModerationState = z.infer<typeof moderationStateSchema>;

export const reportReasonSchema = z.enum([
  "spam",
  "harassment",
  "off_topic",
  "safety",
  "other",
]);
export type ReportReason = z.infer<typeof reportReasonSchema>;

export const reportStatusSchema = z.enum([
  "open",
  "reviewing",
  "actioned",
  "dismissed",
]);
export type ReportStatus = z.infer<typeof reportStatusSchema>;

export const communitySchema = z.object({
  id: z.string(),
  slug: z.string(),
  name: z.string(),
  summary: z.string(),
  description: z.string().nullable(),
  topic: z.string().nullable(),
  visibility: z.enum(["published", "archived"]),
  is_seeded: z.boolean(),
  is_member: z.boolean(),
  membership_role: z.enum(["member", "moderator"]).nullable(),
  created_at: isoDateTime,
});
export type Community = z.infer<typeof communitySchema>;

const authorSchema = z.object({
  name: z.string(),
  is_verified_mentor: z.boolean(),
});

export const postSchema = z.object({
  id: z.string(),
  community: z
    .object({ id: z.string(), name: z.string(), slug: z.string() })
    .optional(),
  author: authorSchema,
  title: z.string().nullable(),
  body: z.string().nullable(),
  moderation_state: moderationStateSchema,
  is_mine: z.boolean(),
  shared_resource: z.object({ title: z.string(), url: z.string() }).nullable(),
  comment_count: z.number().int(),
  version: z.number().int().min(1),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});
export type Post = z.infer<typeof postSchema>;

export const commentSchema = z.object({
  id: z.string(),
  author: authorSchema,
  body: z.string().nullable(),
  moderation_state: moderationStateSchema,
  is_mine: z.boolean(),
  version: z.number().int().min(1),
  created_at: isoDateTime,
});
export type Comment = z.infer<typeof commentSchema>;

export const reportSchema = z.object({
  id: z.string(),
  community: z.object({ id: z.string(), name: z.string() }).optional(),
  subject: z.object({
    type: z.enum(["post", "comment"]),
    id: z.string().nullable(),
    excerpt: z.string().nullable(),
  }),
  reason: reportReasonSchema,
  detail: z.string().nullable(),
  status: reportStatusSchema,
  resolution_note: z.string().nullable(),
  version: z.number().int().min(1),
  handled_at: isoDateTime.nullable(),
  created_at: isoDateTime,
});
export type Report = z.infer<typeof reportSchema>;

const collectionMeta = z.object({
  summary: z.record(z.string(), z.number()).optional(),
  pagination: z.object({
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    per_page: z.number().int(),
  }),
});

const communityCollectionSchema = z.object({
  data: z.array(communitySchema),
  meta: collectionMeta,
});
export type CommunityPage = z.infer<typeof communityCollectionSchema>;

const postCollectionSchema = z.object({
  data: z.array(postSchema),
  meta: collectionMeta,
});
export type PostPage = z.infer<typeof postCollectionSchema>;

const commentCollectionSchema = z.object({
  data: z.array(commentSchema),
  meta: collectionMeta,
});
export type CommentPage = z.infer<typeof commentCollectionSchema>;

const reportCollectionSchema = z.object({
  data: z.array(reportSchema),
  meta: collectionMeta,
});
export type ReportPage = z.infer<typeof reportCollectionSchema>;

type Cursor = { perPage?: number; cursor?: string };

export async function listCommunities(
  params: { search?: string } & Cursor = {},
): Promise<CommunityPage> {
  const query = toQueryString({
    search: params.search,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return communityCollectionSchema.parse(
    await apiFetch(`/api/v1/communities${query}`),
  );
}

export async function getCommunity(id: string): Promise<Community> {
  return communitySchema.parse(
    envelopeData(await apiFetch(`/api/v1/communities/${id}`)),
  );
}

export async function joinCommunity(id: string): Promise<Community> {
  return communitySchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/communities/${id}/membership`, {
        method: "POST",
      }),
    ),
  );
}

export async function leaveCommunity(id: string): Promise<Community> {
  return communitySchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/communities/${id}/membership`, {
        method: "DELETE",
      }),
    ),
  );
}

export async function listFeed(params: Cursor = {}): Promise<PostPage> {
  const query = toQueryString({
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return postCollectionSchema.parse(await apiFetch(`/api/v1/feed${query}`));
}

export async function listCommunityPosts(
  communityId: string,
  params: Cursor = {},
): Promise<PostPage> {
  const query = toQueryString({
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return postCollectionSchema.parse(
    await apiFetch(`/api/v1/communities/${communityId}/posts${query}`),
  );
}

export type CreatePostInput = {
  title?: string | null;
  body: string;
  shared_resource_id?: string | null;
};

export async function createPost(
  communityId: string,
  input: CreatePostInput,
): Promise<Post> {
  return postSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/communities/${communityId}/posts`, {
        method: "POST",
        body: input,
      }),
    ),
  );
}

export async function getPost(id: string): Promise<Post> {
  return postSchema.parse(envelopeData(await apiFetch(`/api/v1/posts/${id}`)));
}

export async function updatePost(
  id: string,
  input: CreatePostInput & { expected_version: number },
): Promise<Post> {
  return postSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/posts/${id}`, { method: "PATCH", body: input }),
    ),
  );
}

export async function deletePost(
  id: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/posts/${id}`, {
    method: "DELETE",
    body: { expected_version: expectedVersion },
  });
}

export async function listComments(
  postId: string,
  params: Cursor = {},
): Promise<CommentPage> {
  const query = toQueryString({
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return commentCollectionSchema.parse(
    await apiFetch(`/api/v1/posts/${postId}/comments${query}`),
  );
}

export async function createComment(
  postId: string,
  body: string,
): Promise<Comment> {
  return commentSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/posts/${postId}/comments`, {
        method: "POST",
        body: { body },
      }),
    ),
  );
}

export async function deleteComment(
  id: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/comments/${id}`, {
    method: "DELETE",
    body: { expected_version: expectedVersion },
  });
}

export type ReportInput = { reason: ReportReason; detail?: string | null };

export async function reportPost(
  postId: string,
  input: ReportInput,
): Promise<Report> {
  return reportSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/posts/${postId}/reports`, {
        method: "POST",
        body: input,
      }),
    ),
  );
}

export async function reportComment(
  commentId: string,
  input: ReportInput,
): Promise<Report> {
  return reportSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/comments/${commentId}/reports`, {
        method: "POST",
        body: input,
      }),
    ),
  );
}

export async function listModerationReports(
  params: { status?: ReportStatus } & Cursor = {},
): Promise<ReportPage> {
  const query = toQueryString({
    status: params.status,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return reportCollectionSchema.parse(
    await apiFetch(`/api/v1/moderation/reports${query}`),
  );
}

export async function resolveReport(
  id: string,
  input: {
    resolution: "actioned" | "dismissed";
    hide_content?: boolean;
    note?: string | null;
    expected_version: number;
  },
): Promise<Report> {
  return reportSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/moderation/reports/${id}/resolution`, {
        method: "PATCH",
        body: input,
      }),
    ),
  );
}
