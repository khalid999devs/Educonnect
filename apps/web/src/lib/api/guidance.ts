import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";
import { toolCategoryRefSchema } from "./tools";

export { toolSchema, type Tool } from "./tools";

/** Exact runtime shapes of the goal-based guidance contract - prompt
 * templates and workflow recipes (openapi.yaml). Tools are their own domain and
 * live in ./tools; the tool schema is re-exported here so prompt and workflow
 * callers keep a single definition of a tool. Tool mutations are imported from
 * ./tools directly by their callers. All content is admin-curated and
 * published-only; nothing is generated here. */

const isoDateTime = z.string();

/** The `{key, name}` category stub shared by tools, prompts and workflows. */
export const categoryRefSchema = toolCategoryRefSchema;

export type CategoryRef = z.infer<typeof categoryRefSchema>;

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

const collectionMeta = z.object({
  pagination: z.object({
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    per_page: z.number().int(),
  }),
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
