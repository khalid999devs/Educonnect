import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

export { saveTool, unsaveTool, dismissTool, undismissTool } from "./tools";

/** Exact runtime shapes of the goal-based guidance contract — tools, prompt
 * templates, workflow recipes, and the combined guidance bundle (openapi.yaml).
 * All content is admin-curated and published-only; nothing is generated here. */

const isoDateTime = z.string();

export const categoryRefSchema = z.object({
  key: z.string(),
  name: z.string(),
});

export type CategoryRef = z.infer<typeof categoryRefSchema>;

export const toolSchema = z.object({
  id: z.string(),
  name: z.string(),
  category: categoryRefSchema,
  purpose: z.string(),
  selection_reason: z.string(),
  use_cases: z.array(z.string()),
  usage_guidance: z.string(),
  limitations: z.string(),
  cost_note: z.string(),
  privacy_note: z.string(),
  url: z.string(),
  provenance: z.string(),
  last_reviewed_at: isoDateTime,
  viewer_state: z.object({ saved: z.boolean(), dismissed: z.boolean() }),
});

export type Tool = z.infer<typeof toolSchema>;

export const promptRelatedToolSchema = z.object({
  id: z.string(),
  name: z.string(),
  url: z.string(),
});

export const promptSchema = z.object({
  id: z.string(),
  title: z.string(),
  category: categoryRefSchema,
  purpose: z.string(),
  template_body: z.string(),
  placeholders: z.array(z.string()),
  expected_output: z.string(),
  integrity_note: z.string(),
  provenance: z.string(),
  related_tools: z.array(promptRelatedToolSchema),
  last_reviewed_at: isoDateTime,
  viewer_state: z.object({
    saved: z.boolean(),
    dismissed: z.boolean(),
    copy_count: z.number().int(),
  }),
});

export type Prompt = z.infer<typeof promptSchema>;

export const workflowStepSchema = z.object({
  number: z.number().int(),
  title: z.string(),
  instruction: z.string(),
  destination_action: z
    .enum([
      "create_task",
      "save_resource",
      "use_tool",
      "use_prompt",
      "use_template",
    ])
    .nullable(),
  tool: promptRelatedToolSchema.nullable(),
  prompt: z.object({ id: z.string(), title: z.string() }).nullable(),
  template: z.object({ id: z.string(), title: z.string() }).nullable(),
});

export type WorkflowStep = z.infer<typeof workflowStepSchema>;

export const workflowSchema = z.object({
  id: z.string(),
  title: z.string(),
  category: categoryRefSchema,
  goal: z.string(),
  expected_outcome: z.string(),
  integrity_note: z.string(),
  provenance: z.string(),
  steps: z.array(workflowStepSchema),
  last_reviewed_at: isoDateTime,
  viewer_state: z.object({ saved: z.boolean(), dismissed: z.boolean() }),
});

export type Workflow = z.infer<typeof workflowSchema>;

const guidanceCategorySchema = z.object({
  key: z.string(),
  name: z.string(),
  description: z.string().nullable(),
});

export type GuidanceCategory = z.infer<typeof guidanceCategorySchema>;

const collectionMeta = z.object({
  pagination: z.object({
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    per_page: z.number().int(),
  }),
});

const toolCollectionSchema = z.object({
  data: z.array(toolSchema),
  meta: collectionMeta,
});
const promptCollectionSchema = z.object({
  data: z.array(promptSchema),
  meta: collectionMeta,
});
const workflowCollectionSchema = z.object({
  data: z.array(workflowSchema),
  meta: collectionMeta,
});

export type GuidancePreference = "all" | "saved" | "dismissed" | "none";

type ListParams = {
  category?: string;
  preference?: GuidancePreference;
  search?: string;
  perPage?: number;
  cursor?: string;
};

function listQuery(params: ListParams): string {
  return toQueryString({
    category: params.category,
    preference: params.preference,
    search: params.search,
    per_page: params.perPage,
    cursor: params.cursor,
  });
}

export async function listTools(
  params: ListParams = {},
): Promise<z.infer<typeof toolCollectionSchema>> {
  return toolCollectionSchema.parse(
    await apiFetch(`/api/v1/tools${listQuery(params)}`),
  );
}

export async function listPrompts(
  params: ListParams = {},
): Promise<z.infer<typeof promptCollectionSchema>> {
  return promptCollectionSchema.parse(
    await apiFetch(`/api/v1/prompts${listQuery(params)}`),
  );
}

export async function listWorkflows(
  params: ListParams = {},
): Promise<z.infer<typeof workflowCollectionSchema>> {
  return workflowCollectionSchema.parse(
    await apiFetch(`/api/v1/workflows${listQuery(params)}`),
  );
}

/** The transparent published guidance bundle assembled for one goal. */
export const guidanceBundleSchema = z.object({
  category: guidanceCategorySchema,
  tools: z.array(toolSchema),
  prompts: z.array(promptSchema),
  workflows: z.array(workflowSchema),
  templates: z.array(
    z.object({
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
          format: z.enum(["markdown", "plain"]),
          body: z.string(),
        })
        .nullable(),
      last_reviewed_at: isoDateTime,
      viewer_state: z.object({
        saved: z.boolean(),
        dismissed: z.boolean(),
        active_copy_count: z.number().int(),
      }),
    }),
  ),
});

export type GuidanceBundle = z.infer<typeof guidanceBundleSchema>;

export async function getGuidance(category: string): Promise<GuidanceBundle> {
  return guidanceBundleSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/guidance${toQueryString({ category })}`),
    ),
  );
}

/* Save/dismiss are mutually exclusive per contract (each clears the other).
   Tool preference mutations are re-exported from ./tools above. */

export async function savePrompt(promptId: string): Promise<void> {
  await apiFetch(`/api/v1/prompts/${promptId}/saved`, { method: "PUT" });
}
export async function unsavePrompt(promptId: string): Promise<void> {
  await apiFetch(`/api/v1/prompts/${promptId}/saved`, { method: "DELETE" });
}
export async function dismissPrompt(promptId: string): Promise<void> {
  await apiFetch(`/api/v1/prompts/${promptId}/dismissed`, { method: "PUT" });
}
export async function undismissPrompt(promptId: string): Promise<void> {
  await apiFetch(`/api/v1/prompts/${promptId}/dismissed`, { method: "DELETE" });
}

export async function saveWorkflow(workflowId: string): Promise<void> {
  await apiFetch(`/api/v1/workflows/${workflowId}/saved`, { method: "PUT" });
}
export async function unsaveWorkflow(workflowId: string): Promise<void> {
  await apiFetch(`/api/v1/workflows/${workflowId}/saved`, { method: "DELETE" });
}
export async function dismissWorkflow(workflowId: string): Promise<void> {
  await apiFetch(`/api/v1/workflows/${workflowId}/dismissed`, {
    method: "PUT",
  });
}
export async function undismissWorkflow(workflowId: string): Promise<void> {
  await apiFetch(`/api/v1/workflows/${workflowId}/dismissed`, {
    method: "DELETE",
  });
}

/** Records a private, bounded copy event (the counter is best-effort). */
export async function recordPromptCopy(promptId: string): Promise<Prompt> {
  return promptSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/prompts/${promptId}/copies`, { method: "POST" }),
    ),
  );
}

/**
 * Enumerate goal categories from the published catalog. There is no dedicated
 * categories endpoint (curation is admin-owned), so distinct categories are
 * unioned from the first page of each published listing. Empty catalog yields
 * an empty list and an honest "being curated" state.
 */
export async function listGuidanceCategories(): Promise<CategoryRef[]> {
  const [tools, prompts, workflows] = await Promise.all([
    listTools({ perPage: 50 }),
    listPrompts({ perPage: 50 }),
    listWorkflows({ perPage: 50 }),
  ]);

  const byKey = new Map<string, CategoryRef>();

  for (const item of [...tools.data, ...prompts.data, ...workflows.data]) {
    if (!byKey.has(item.category.key)) {
      byKey.set(item.category.key, item.category);
    }
  }

  return Array.from(byKey.values()).sort((a, b) =>
    a.name.localeCompare(b.name),
  );
}
