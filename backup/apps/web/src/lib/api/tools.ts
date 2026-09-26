import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/**
 * The Tools domain client (openapi.yaml: Tools tag).
 *
 * Everything about a tool is admin-curated and published-only. Nothing on this
 * surface is generated: the AI ranker may reorder and explain the PostgreSQL
 * candidate set and nothing else, which is why `ScenarioSearchResult` is a
 * `Tool` plus a single `match_reason` string.
 *
 * `listTools` lives here rather than in `guidance.ts` because tools are their
 * own domain; `guidance.ts` re-exports the tool schemas so the goal-bundle
 * contract keeps one definition of a tool.
 */

const isoDateTime = z.string();

export const toolCategoryRefSchema = z.object({
  key: z.string(),
  name: z.string(),
});

export type ToolCategoryRef = z.infer<typeof toolCategoryRefSchema>;

export const toolViewerStateSchema = z.object({
  saved: z.boolean(),
  dismissed: z.boolean(),
});

export const toolSchema = z.object({
  id: z.string(),
  name: z.string(),
  category: toolCategoryRefSchema,
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
  viewer_state: toolViewerStateSchema,
});

export type Tool = z.infer<typeof toolSchema>;

/** The full curated goal dimension. Small, identical per student, uncursored. */
export const toolCategorySchema = z.object({
  id: z.string(),
  key: z.string(),
  name: z.string(),
  description: z.string().nullable(),
  sort_order: z.number().int(),
});

export type ToolCategory = z.infer<typeof toolCategorySchema>;

const toolCategoryCollectionSchema = z.object({
  data: z.array(toolCategorySchema),
});

const toolCollectionSchema = z.object({
  data: z.array(toolSchema),
  meta: z.object({
    pagination: z.object({
      next_cursor: z.string().nullable(),
      previous_cursor: z.string().nullable(),
      per_page: z.number().int(),
    }),
  }),
});

export type ToolCollection = z.infer<typeof toolCollectionSchema>;

export type ToolPreference = "all" | "saved" | "dismissed" | "none";
export type ToolSort = "name" | "-last_reviewed_at";

export type ListToolsParams = {
  search?: string;
  category?: string;
  /** Server-side preference filter. Never filter viewer_state client-side. */
  preference?: ToolPreference;
  sort?: ToolSort;
  perPage?: number;
  cursor?: string;
};

export async function listTools(
  params: ListToolsParams = {},
): Promise<ToolCollection> {
  return toolCollectionSchema.parse(
    await apiFetch(
      `/api/v1/tools${toQueryString({
        search: params.search,
        category: params.category,
        preference: params.preference,
        sort: params.sort,
        per_page: params.perPage,
        cursor: params.cursor,
      })}`,
    ),
  );
}

/**
 * The authoritative goal dimension. Replaces the old three-way fan-out over
 * the first page of each published listing, which silently dropped every
 * category that only appeared past page one.
 */
export async function listToolCategories(): Promise<ToolCategory[]> {
  return toolCategoryCollectionSchema.parse(
    await apiFetch("/api/v1/tool-categories"),
  ).data;
}

/* ---------------------------------------------------------------- scenario */

export const scenarioSearchResultSchema = toolSchema.extend({
  /** AI-derived when `ai_ranked` is true. Always rendered as inert text. */
  match_reason: z.string(),
});

export type ScenarioSearchResult = z.infer<typeof scenarioSearchResultSchema>;

export const scenarioSearchSchema = z.object({
  results: z.array(scenarioSearchResultSchema),
  /** False when the deterministic keyword ranker answered. Caption honestly. */
  ai_ranked: z.boolean(),
  cached: z.boolean(),
  provider: z.string(),
  model: z.string(),
  disclaimer: z.string(),
});

export type ScenarioSearch = z.infer<typeof scenarioSearchSchema>;

const scenarioSearchDataSchema = z.object({
  scenario_search: scenarioSearchSchema,
});

/**
 * Rank the curated catalog against the student's own words.
 *
 * The endpoint is cache-first (15 minutes server-side) and always degrades to
 * an unranked candidate set, so there is no failure path that shows nothing.
 * A transport-level failure still throws `ApiError` and the caller renders an
 * ErrorState; a ranker failure is not an error and must render normally.
 */
export async function searchToolsByScenario(input: {
  scenario: string;
  category?: string;
}): Promise<ScenarioSearch> {
  return scenarioSearchDataSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/tools/scenario-search", {
        method: "POST",
        body: {
          scenario: input.scenario,
          ...(input.category === undefined ? {} : { category: input.category }),
        },
      }),
    ),
  ).scenario_search;
}

/* -------------------------------------------------------------- preference */

/** Save and dismiss are mutually exclusive per contract: each clears the other. */

export async function saveTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/saved`, { method: "PUT" });
}

export async function unsaveTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/saved`, { method: "DELETE" });
}

export async function dismissTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/dismissed`, { method: "PUT" });
}

export async function undismissTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/dismissed`, { method: "DELETE" });
}
