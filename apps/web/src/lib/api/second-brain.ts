import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/** Exact runtime shapes of the Second Brain contract (openapi.yaml): source-
 * centered knowledge items with immutable provenance, editable citation/notes,
 * private tags, collections, and typed item-to-item connections. */

const isoDateTime = z.string();

export const collectionKindSchema = z.enum([
  "course",
  "project",
  "research",
  "goal",
  "general",
]);

export type CollectionKind = z.infer<typeof collectionKindSchema>;

export const sourceTypeSchema = z.enum(["resource", "link", "none"]);
export type KnowledgeSourceType = z.infer<typeof sourceTypeSchema>;

export const relationSchema = z.enum([
  "related",
  "supports",
  "contradicts",
  "builds_on",
]);
export type KnowledgeRelation = z.infer<typeof relationSchema>;

export const collectionSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  name: z.string(),
  description: z.string().nullable(),
  kind: collectionKindSchema,
  item_count: z.number().int(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type Collection = z.infer<typeof collectionSchema>;

const citationSchema = z.object({
  authors: z.string().nullable(),
  published_year: z.number().int().nullable(),
  venue: z.string().nullable(),
  doi: z.string().nullable(),
});

const sourceSchema = z.object({
  type: sourceTypeSchema,
  url: z.string().nullable(),
  resource: z
    .object({ id: z.string(), title: z.string(), type: z.string() })
    .nullable(),
});

export const knowledgeItemSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  title: z.string(),
  summary: z.string().nullable(),
  source: sourceSchema,
  citation: citationSchema,
  tags: z.array(z.string()),
  collections: z
    .array(
      z.object({
        id: z.string(),
        name: z.string(),
        kind: collectionKindSchema,
      }),
    )
    .optional()
    .default([]),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type KnowledgeItem = z.infer<typeof knowledgeItemSchema>;

export const knowledgeNoteSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  body: z.string(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type KnowledgeNote = z.infer<typeof knowledgeNoteSchema>;

export const knowledgeItemDetailSchema = knowledgeItemSchema.extend({
  notes: z.array(knowledgeNoteSchema),
  links: z.array(
    z.object({
      id: z.string(),
      direction: z.enum(["outgoing", "incoming"]),
      relation_type: relationSchema,
      item: z.object({ id: z.string(), title: z.string() }).nullable(),
    }),
  ),
  research_topics: z.array(
    z.object({
      id: z.string(),
      title: z.string(),
      reading_status: z.enum(["to_read", "reading", "read"]).nullable(),
    }),
  ),
});

export type KnowledgeItemDetail = z.infer<typeof knowledgeItemDetailSchema>;

const brainMeta = z.object({
  summary: z.object({ total: z.number().int() }).optional(),
  pagination: z.object({
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    per_page: z.number().int(),
  }),
});

const knowledgeCollectionSchema = z.object({
  data: z.array(knowledgeItemSchema),
  meta: brainMeta,
});

const collectionListSchema = z.object({
  data: z.array(collectionSchema),
  meta: brainMeta,
});

export type KnowledgeSearchParams = {
  search?: string;
  collectionId?: string;
  topicId?: string;
  tag?: string;
  sourceType?: KnowledgeSourceType;
  sort?: "created_at" | "-created_at" | "updated_at" | "-updated_at";
  perPage?: number;
  cursor?: string;
};

export async function searchKnowledge(
  params: KnowledgeSearchParams = {},
): Promise<z.infer<typeof knowledgeCollectionSchema>> {
  const query = toQueryString({
    search: params.search,
    collection_id: params.collectionId,
    topic_id: params.topicId,
    tag: params.tag,
    source_type: params.sourceType,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return knowledgeCollectionSchema.parse(
    await apiFetch(`/api/v1/knowledge${query}`),
  );
}

export async function getKnowledgeItem(
  itemId: string,
): Promise<KnowledgeItemDetail> {
  return knowledgeItemDetailSchema.parse(
    envelopeData(await apiFetch(`/api/v1/knowledge/${itemId}`)),
  );
}

export type CreateKnowledgeInput = {
  title: string;
  summary?: string | null;
  source_type: KnowledgeSourceType;
  resource_id?: string | null;
  url?: string | null;
};

export async function createKnowledgeItem(
  input: CreateKnowledgeInput,
): Promise<KnowledgeItem> {
  return knowledgeItemSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/knowledge", { method: "POST", body: input }),
    ),
  );
}

export async function updateKnowledgeItem(
  itemId: string,
  input: {
    expected_version: number;
    title: string;
    summary?: string | null;
    authors?: string | null;
    published_year?: number | null;
    venue?: string | null;
    doi?: string | null;
  },
): Promise<KnowledgeItem> {
  return knowledgeItemSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/knowledge/${itemId}`, {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

export async function deleteKnowledgeItem(
  itemId: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/knowledge/${itemId}`, {
    method: "DELETE",
    body: { expected_version: expectedVersion },
  });
}

export async function addKnowledgeNote(
  itemId: string,
  body: string,
): Promise<KnowledgeNote> {
  return knowledgeNoteSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/knowledge/${itemId}/notes`, {
        method: "POST",
        body: { body },
      }),
    ),
  );
}

export async function updateKnowledgeNote(
  itemId: string,
  noteId: string,
  input: { body: string; expected_version: number },
): Promise<KnowledgeNote> {
  return knowledgeNoteSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/knowledge/${itemId}/notes/${noteId}`, {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

export async function deleteKnowledgeNote(
  itemId: string,
  noteId: string,
): Promise<void> {
  await apiFetch(`/api/v1/knowledge/${itemId}/notes/${noteId}`, {
    method: "DELETE",
  });
}

export async function syncKnowledgeTags(
  itemId: string,
  tags: string[],
): Promise<void> {
  await apiFetch(`/api/v1/knowledge/${itemId}/tags`, {
    method: "PUT",
    body: { tags },
  });
}

export async function syncKnowledgeCollections(
  itemId: string,
  collectionIds: string[],
): Promise<void> {
  await apiFetch(`/api/v1/knowledge/${itemId}/collections`, {
    method: "PUT",
    body: { collection_ids: collectionIds },
  });
}

export async function listCollections(
  params: { perPage?: number; cursor?: string } = {},
): Promise<z.infer<typeof collectionListSchema>> {
  const query = toQueryString({
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return collectionListSchema.parse(
    await apiFetch(`/api/v1/collections${query}`),
  );
}

export async function createCollection(input: {
  name: string;
  description?: string | null;
  kind?: CollectionKind | null;
}): Promise<Collection> {
  return collectionSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/collections", { method: "POST", body: input }),
    ),
  );
}
