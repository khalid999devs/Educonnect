import { z } from "zod";

import { categoryRefSchema } from "./guidance";
import { apiFetch, envelopeData, toQueryString } from "./http";

/** Exact runtime shapes of the curated template library and the user's
 * independent editable copies (openapi.yaml). Copies are pinned to an
 * immutable source version; the source itself never changes. */

const isoDateTime = z.string();

export const templateFormatSchema = z.enum(["markdown", "plain"]);

export const templateSchema = z.object({
  id: z.string(),
  title: z.string(),
  category: categoryRefSchema,
  summary: z.string(),
  badge: z.literal("approved_free"),
  integrity_note: z.string(),
  provenance: z.string(),
  latest_version: z
    .object({
      number: z.number().int(),
      format: templateFormatSchema,
      body: z.string(),
    })
    .nullable(),
  last_reviewed_at: isoDateTime,
  viewer_state: z.object({
    saved: z.boolean(),
    dismissed: z.boolean(),
    active_copy_count: z.number().int(),
  }),
});

export type Template = z.infer<typeof templateSchema>;

export const templateCopySchema = z.object({
  id: z.string(),
  destination: z.enum(["dashboard", "course"]),
  course: z.object({ id: z.string(), title: z.string() }).nullable(),
  source: z.object({
    template_id: z.string().nullable(),
    template_title: z.string().nullable(),
    version_number: z.number().int().nullable(),
  }),
  title: z.string(),
  format: templateFormatSchema,
  body: z.string(),
  version: z.number().int().min(1),
  archived_at: isoDateTime.nullable(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type TemplateCopy = z.infer<typeof templateCopySchema>;

const collectionMeta = z.object({
  pagination: z.object({
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    per_page: z.number().int(),
  }),
});

const templateCollectionSchema = z.object({
  data: z.array(templateSchema),
  meta: collectionMeta,
});

const templateCopyCollectionSchema = z.object({
  data: z.array(templateCopySchema),
  meta: collectionMeta,
});

export type TemplateListParams = {
  search?: string;
  category?: string;
  preference?: "all" | "saved" | "dismissed" | "none";
  sort?: "title" | "-last_reviewed_at";
  perPage?: number;
  cursor?: string;
};

export async function listTemplates(
  params: TemplateListParams = {},
): Promise<z.infer<typeof templateCollectionSchema>> {
  const query = toQueryString({
    search: params.search,
    category: params.category,
    preference: params.preference,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return templateCollectionSchema.parse(
    await apiFetch(`/api/v1/templates${query}`),
  );
}

export async function getTemplate(templateId: string): Promise<Template> {
  return templateSchema.parse(
    envelopeData(await apiFetch(`/api/v1/templates/${templateId}`)),
  );
}

export async function saveTemplate(templateId: string): Promise<void> {
  await apiFetch(`/api/v1/templates/${templateId}/saved`, { method: "PUT" });
}
export async function unsaveTemplate(templateId: string): Promise<void> {
  await apiFetch(`/api/v1/templates/${templateId}/saved`, { method: "DELETE" });
}
export async function dismissTemplate(templateId: string): Promise<void> {
  await apiFetch(`/api/v1/templates/${templateId}/dismissed`, {
    method: "PUT",
  });
}
export async function undismissTemplate(templateId: string): Promise<void> {
  await apiFetch(`/api/v1/templates/${templateId}/dismissed`, {
    method: "DELETE",
  });
}

export type CopyDestination =
  { destination: "dashboard" } | { destination: "course"; course_id: string };

/** Copies the latest published version to a destination, creating an
 * independent editable copy. An identical active copy is returned unchanged. */
export async function copyTemplate(
  templateId: string,
  destination: CopyDestination,
): Promise<TemplateCopy> {
  return templateCopySchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/templates/${templateId}/copies`, {
        method: "POST",
        body: destination,
      }),
    ),
  );
}

export type TemplateCopyListParams = {
  destination?: "dashboard" | "course";
  courseId?: string;
  includeArchived?: boolean;
  sort?: "-updated_at" | "updated_at";
  perPage?: number;
  cursor?: string;
};

export async function listTemplateCopies(
  params: TemplateCopyListParams = {},
): Promise<z.infer<typeof templateCopyCollectionSchema>> {
  const query = toQueryString({
    destination: params.destination,
    course_id: params.courseId,
    include_archived: params.includeArchived,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return templateCopyCollectionSchema.parse(
    await apiFetch(`/api/v1/template-copies${query}`),
  );
}

export async function getTemplateCopy(copyId: string): Promise<TemplateCopy> {
  return templateCopySchema.parse(
    envelopeData(await apiFetch(`/api/v1/template-copies/${copyId}`)),
  );
}

export async function updateTemplateCopy(
  copyId: string,
  input: { expected_version: number; title: string; body: string },
): Promise<TemplateCopy> {
  return templateCopySchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/template-copies/${copyId}`, {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

export async function archiveTemplateCopy(
  copyId: string,
  expectedVersion: number,
): Promise<TemplateCopy> {
  return templateCopySchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/template-copies/${copyId}/archive`, {
        method: "PUT",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}

export async function restoreTemplateCopy(
  copyId: string,
  expectedVersion: number,
): Promise<TemplateCopy> {
  return templateCopySchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/template-copies/${copyId}/archive`, {
        method: "DELETE",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}
