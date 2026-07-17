import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";
import { sourceTypeSchema } from "./second-brain";

/** Exact runtime shapes of the lightweight research topic tracker
 * (openapi.yaml): private topics with keywords and a reading list. */

const isoDateTime = z.string();

export const readingStatusSchema = z.enum(["to_read", "reading", "read"]);
export type ReadingStatus = z.infer<typeof readingStatusSchema>;

export const researchSourceSchema = z.object({
  item: z.object({
    id: z.string(),
    title: z.string(),
    source_type: sourceTypeSchema,
    authors: z.string().nullable(),
    published_year: z.number().int().nullable(),
    venue: z.string().nullable(),
    doi: z.string().nullable(),
  }),
  reading_status: readingStatusSchema.nullable(),
});

export type ResearchSource = z.infer<typeof researchSourceSchema>;

export const researchTopicSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  title: z.string(),
  description: z.string().nullable(),
  keywords: z.array(z.string()),
  source_count: z.number().int(),
  sources: z.array(researchSourceSchema).optional().default([]),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type ResearchTopic = z.infer<typeof researchTopicSchema>;

const topicCollectionSchema = z.object({
  data: z.array(researchTopicSchema),
  meta: z.object({
    pagination: z.object({
      next_cursor: z.string().nullable(),
      previous_cursor: z.string().nullable(),
      per_page: z.number().int(),
    }),
  }),
});

export async function listResearchTopics(
  params: { perPage?: number; cursor?: string } = {},
): Promise<z.infer<typeof topicCollectionSchema>> {
  const query = toQueryString({
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return topicCollectionSchema.parse(
    await apiFetch(`/api/v1/research-topics${query}`),
  );
}

export async function getResearchTopic(
  topicId: string,
): Promise<ResearchTopic> {
  return researchTopicSchema.parse(
    envelopeData(await apiFetch(`/api/v1/research-topics/${topicId}`)),
  );
}

export async function createResearchTopic(input: {
  title: string;
  description?: string | null;
  keywords?: string[] | null;
}): Promise<ResearchTopic> {
  return researchTopicSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/research-topics", {
        method: "POST",
        body: input,
      }),
    ),
  );
}

export async function updateResearchTopic(
  topicId: string,
  input: {
    title: string;
    keywords: string[];
    description?: string | null;
    expected_version: number;
  },
): Promise<ResearchTopic> {
  return researchTopicSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/research-topics/${topicId}`, {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

export async function attachResearchSource(
  topicId: string,
  knowledgeItemId: string,
  readingStatus?: ReadingStatus,
): Promise<ResearchTopic> {
  return researchTopicSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/research-topics/${topicId}/sources`, {
        method: "POST",
        body: {
          knowledge_item_id: knowledgeItemId,
          ...(readingStatus ? { reading_status: readingStatus } : {}),
        },
      }),
    ),
  );
}

export async function updateResearchSource(
  topicId: string,
  knowledgeItemId: string,
  readingStatus: ReadingStatus,
): Promise<ResearchTopic> {
  return researchTopicSchema.parse(
    envelopeData(
      await apiFetch(
        `/api/v1/research-topics/${topicId}/sources/${knowledgeItemId}`,
        { method: "PUT", body: { reading_status: readingStatus } },
      ),
    ),
  );
}
