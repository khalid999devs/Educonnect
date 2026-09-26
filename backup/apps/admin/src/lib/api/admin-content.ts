import { apiFetch, envelopeData, toQueryString } from "./http";
import { parsePage, parseResource, type Page } from "./pagination";
import { z } from "zod";

export const CONTENT_STATES = [
  "draft",
  "in_review",
  "published",
  "archived",
] as const;

export type ContentState = (typeof CONTENT_STATES)[number];

/** The content lifecycle types share a URL segment and a transition contract. */
export type ContentType = "tools" | "prompts" | "workflows" | "templates";

export const categorySchema = z.object({
  slug: z.string(),
  name: z.string(),
  description: z.string(),
});

export type Category = z.infer<typeof categorySchema>;

export async function listCategories(): Promise<Category[]> {
  return z
    .array(categorySchema)
    .parse(envelopeData(await apiFetch("/api/v1/admin/content/categories")));
}

const lifecycleFields = {
  id: z.string(),
  state: z.enum(CONTENT_STATES),
  last_reviewed_at: z.string().nullable(),
  published_at: z.string().nullable(),
  archived_at: z.string().nullable(),
  version: z.number(),
  created_at: z.string(),
  updated_at: z.string(),
};

export const adminToolSchema = z.object({
  ...lifecycleFields,
  name: z.string(),
  category: z.object({ slug: z.string(), name: z.string() }).nullable(),
  purpose: z.string(),
  selection_reason: z.string(),
  use_cases: z.array(z.string()),
  usage_guidance: z.string(),
  limitations: z.string(),
  cost_note: z.string(),
  privacy_note: z.string(),
  url: z.string(),
  provenance: z.string(),
});

export type AdminTool = z.infer<typeof adminToolSchema>;

export type ToolContent = {
  category_slug: string;
  name: string;
  purpose: string;
  selection_reason: string;
  use_cases: string[];
  usage_guidance: string;
  limitations: string;
  cost_note: string;
  privacy_note: string;
  url: string;
  provenance: string;
};

export async function listTools(filters: {
  state?: string;
  cursor?: string;
}): Promise<Page<AdminTool>> {
  const query = toQueryString({
    state: filters.state || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/content/tools${query}`),
    adminToolSchema,
  );
}

export async function getTool(id: string): Promise<AdminTool> {
  return parseResource(
    await apiFetch(`/api/v1/admin/content/tools/${id}`),
    adminToolSchema,
  );
}

export async function createTool(content: ToolContent): Promise<AdminTool> {
  return parseResource(
    await apiFetch("/api/v1/admin/content/tools", {
      method: "POST",
      body: content,
    }),
    adminToolSchema,
  );
}

export async function updateTool(
  id: string,
  content: ToolContent,
  expectedVersion: number,
): Promise<AdminTool> {
  return parseResource(
    await apiFetch(`/api/v1/admin/content/tools/${id}`, {
      method: "PUT",
      body: { ...content, expected_version: expectedVersion },
    }),
    adminToolSchema,
  );
}

export const adminPromptSchema = z.object({
  ...lifecycleFields,
  title: z.string(),
  category: z.object({ slug: z.string(), name: z.string() }).nullable(),
  purpose: z.string(),
  template_body: z.string(),
  placeholders: z.array(z.string()),
  expected_output: z.string(),
  integrity_note: z.string(),
  provenance: z.string(),
  related_tools: z.array(z.object({ id: z.string(), name: z.string() })),
});

export type AdminPrompt = z.infer<typeof adminPromptSchema>;

export type PromptContent = {
  category_slug: string;
  title: string;
  purpose: string;
  template_body: string;
  placeholders: string[];
  expected_output: string;
  integrity_note: string;
  provenance: string;
  related_tools: string[];
};

export const workflowStepSchema = z.object({
  number: z.number(),
  title: z.string(),
  instruction: z.string(),
  destination_action: z.string().nullable(),
});

export const adminWorkflowSchema = z.object({
  ...lifecycleFields,
  title: z.string(),
  category: z.object({ slug: z.string(), name: z.string() }).nullable(),
  goal: z.string(),
  expected_outcome: z.string(),
  integrity_note: z.string(),
  provenance: z.string(),
  steps: z.array(workflowStepSchema),
});

export type AdminWorkflow = z.infer<typeof adminWorkflowSchema>;

export type WorkflowStepContent = {
  title: string;
  instruction: string;
  destination_action: string | null;
};

export type WorkflowContent = {
  category_slug: string;
  title: string;
  goal: string;
  expected_outcome: string;
  integrity_note: string;
  provenance: string;
  steps: WorkflowStepContent[];
};

export const DESTINATION_ACTIONS = [
  "create_task",
  "save_resource",
  "use_tool",
  "use_prompt",
  "use_template",
] as const;

export async function listPrompts(filters: {
  state?: string;
  cursor?: string;
}): Promise<Page<AdminPrompt>> {
  const query = toQueryString({
    state: filters.state || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/content/prompts${query}`),
    adminPromptSchema,
  );
}

export async function createPrompt(
  content: PromptContent,
): Promise<AdminPrompt> {
  return parseResource(
    await apiFetch("/api/v1/admin/content/prompts", {
      method: "POST",
      body: content,
    }),
    adminPromptSchema,
  );
}

export async function updatePrompt(
  id: string,
  content: PromptContent,
  expectedVersion: number,
): Promise<AdminPrompt> {
  return parseResource(
    await apiFetch(`/api/v1/admin/content/prompts/${id}`, {
      method: "PUT",
      body: { ...content, expected_version: expectedVersion },
    }),
    adminPromptSchema,
  );
}

export async function listWorkflows(filters: {
  state?: string;
  cursor?: string;
}): Promise<Page<AdminWorkflow>> {
  const query = toQueryString({
    state: filters.state || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/content/workflows${query}`),
    adminWorkflowSchema,
  );
}

export async function createWorkflow(
  content: WorkflowContent,
): Promise<AdminWorkflow> {
  return parseResource(
    await apiFetch("/api/v1/admin/content/workflows", {
      method: "POST",
      body: content,
    }),
    adminWorkflowSchema,
  );
}

export async function updateWorkflow(
  id: string,
  content: WorkflowContent,
  expectedVersion: number,
): Promise<AdminWorkflow> {
  return parseResource(
    await apiFetch(`/api/v1/admin/content/workflows/${id}`, {
      method: "PUT",
      body: { ...content, expected_version: expectedVersion },
    }),
    adminWorkflowSchema,
  );
}

export const TEMPLATE_FORMATS = ["markdown", "plain"] as const;

export const adminTemplateSchema = z.object({
  ...lifecycleFields,
  title: z.string(),
  category: z.object({ slug: z.string(), name: z.string() }).nullable(),
  summary: z.string(),
  badge: z.string(),
  integrity_note: z.string(),
  provenance: z.string(),
  latest_version: z
    .object({
      number: z.number(),
      format: z.enum(TEMPLATE_FORMATS),
      body: z.string(),
    })
    .nullable(),
});

export type AdminTemplate = z.infer<typeof adminTemplateSchema>;

export type TemplateContent = {
  category_slug: string;
  title: string;
  summary: string;
  integrity_note: string;
  provenance: string;
  format: (typeof TEMPLATE_FORMATS)[number];
  body: string;
  change_note: string | null;
};

export async function listTemplates(filters: {
  state?: string;
  cursor?: string;
}): Promise<Page<AdminTemplate>> {
  const query = toQueryString({
    state: filters.state || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/content/templates${query}`),
    adminTemplateSchema,
  );
}

export async function createTemplate(
  content: TemplateContent,
): Promise<AdminTemplate> {
  return parseResource(
    await apiFetch("/api/v1/admin/content/templates", {
      method: "POST",
      body: { ...content, change_note: content.change_note ?? undefined },
    }),
    adminTemplateSchema,
  );
}

export async function updateTemplate(
  id: string,
  content: TemplateContent,
  expectedVersion: number,
): Promise<AdminTemplate> {
  return parseResource(
    await apiFetch(`/api/v1/admin/content/templates/${id}`, {
      method: "PUT",
      body: {
        ...content,
        change_note: content.change_note ?? undefined,
        expected_version: expectedVersion,
      },
    }),
    adminTemplateSchema,
  );
}

export type ContentTransition =
  "submit_for_review" | "publish" | "archive" | "return_to_draft";

/** Advance any catalog record through the shared curation lifecycle. */
export async function transitionContent(input: {
  type: ContentType;
  id: string;
  transition: ContentTransition;
  expectedVersion: number;
  reason: string;
}): Promise<unknown> {
  return apiFetch(`/api/v1/admin/content/${input.type}/${input.id}/lifecycle`, {
    method: "PATCH",
    body: {
      transition: input.transition,
      expected_version: input.expectedVersion,
      reason: input.reason,
    },
  });
}

/** The transitions available from a given state (mirrors the API state machine). */
export function availableTransitions(state: ContentState): ContentTransition[] {
  switch (state) {
    case "draft":
      return ["submit_for_review"];
    case "in_review":
      return ["publish", "return_to_draft"];
    case "published":
      return ["archive", "return_to_draft"];
    case "archived":
      return ["return_to_draft"];
  }
}
