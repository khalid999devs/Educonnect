/** Query-key families for the authenticated app (ADR-0022). Mutations
 * invalidate whole families so every window/list stays truthful. */

export const plannerKeys = {
  all: ["planner"] as const,
  agenda: (timezone: string, date: string) =>
    ["planner", "agenda", timezone, date] as const,
  weekly: (timezone: string, weekStart: string) =>
    ["planner", "weekly", timezone, weekStart] as const,
  /** The all-tasks / archived list, which reads GET /tasks directly rather than
   * the weekly window, so undated tasks are reachable. */
  tasks: (params: Record<string, string | number | boolean | undefined>) =>
    ["planner", "tasks", params] as const,
  /** The focus-session task picker: a bounded, cursor-drained option list. */
  taskPicker: () => ["planner", "task-picker"] as const,
};

export const resourceKeys = {
  all: ["resources"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["resources", "list", params] as const,
  /** Directory counts are derived from resources, so they deliberately live in
   * this family: every resource mutation already invalidates it. */
  directories: () => ["resources", "directories"] as const,
};

export const courseKeys = {
  all: ["courses"] as const,
  /** Parameterised so the active roster and the Settings "all statuses" fetch
   * cannot read each other's cache. The default `{}` is deliberate: it keeps a
   * bare `courseKeys.list()` compiling and behaviourally identical. */
  list: (params: Record<string, string | number | boolean | undefined> = {}) =>
    ["courses", "list", params] as const,
};

export const toolCategoryKeys = {
  all: ["tool-categories"] as const,
  list: () => ["tool-categories", "list"] as const,
};

export const scenarioKeys = {
  all: ["scenario-search"] as const,
  search: (params: Record<string, string | number | boolean | undefined>) =>
    ["scenario-search", "results", params] as const,
};

/** Curated catalog families. Tools, prompts and workflows are three separate
 * endpoints with independent cursors, so each gets its own family with its own
 * cursor rather than sharing one. */
export const toolKeys = {
  all: ["tools"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["tools", "list", params] as const,
};

export const promptKeys = {
  all: ["prompts"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["prompts", "list", params] as const,
};

export const workflowKeys = {
  all: ["workflows"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["workflows", "list", params] as const,
};

export const studyKeys = {
  all: ["study"] as const,
  /** Parameterised because the artifact list is filtered and sorted: an item id
   * alone cannot key `?sort=` or `?status=` without silently sharing a cache
   * entry. Every caller passes at least `{ itemId }`. */
  artifacts: (params: Record<string, string | number | boolean | undefined>) =>
    ["study", "artifacts", params] as const,
  artifact: (id: string) => ["study", "artifact", id] as const,
};

export const templateKeys = {
  all: ["templates"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["templates", "list", params] as const,
  copies: (params: Record<string, string | number | boolean | undefined>) =>
    ["templates", "copies", params] as const,
};

export const intakeKeys = {
  all: ["intake"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["intake", "list", params] as const,
  item: (id: string) => ["intake", "item", id] as const,
  suggestions: (id: string) => ["intake", "suggestions", id] as const,
  /** One bounded window of an item's extracted text. It deliberately lives in
   * the intake family: a retry or cancel already invalidates it. */
  extraction: (id: string, offset: number, limit: number) =>
    ["intake", "extraction", id, offset, limit] as const,
};

export const brainKeys = {
  all: ["brain"] as const,
  search: (params: Record<string, string | number | boolean | undefined>) =>
    ["brain", "search", params] as const,
  item: (id: string) => ["brain", "item", id] as const,
  collections: () => ["brain", "collections"] as const,
};

export const communityKeys = {
  all: ["community"] as const,
  communities: (
    params: Record<string, string | number | boolean | undefined>,
  ) => ["community", "communities", params] as const,
  community: (id: string) => ["community", "community", id] as const,
  feed: () => ["community", "feed"] as const,
  members: (
    communityId: string,
    params?: Record<string, string | number | boolean | undefined>,
  ) =>
    params
      ? (["community", "members", communityId, params] as const)
      : (["community", "members", communityId] as const),
  posts: (communityId: string) => ["community", "posts", communityId] as const,
  post: (id: string) => ["community", "post", id] as const,
  comments: (postId: string) => ["community", "comments", postId] as const,
  moderation: (params: Record<string, string | number | boolean | undefined>) =>
    ["community", "moderation", params] as const,
};

/** The single authoritative progress read model. The dashboard delegates to the
 * same query server-side, so this family and dashboard data cannot disagree. */
export const progressKeys = {
  all: ["progress"] as const,
  overview: (timezone: string, window: "week" | "month" | "term") =>
    ["progress", "overview", timezone, window] as const,
};

export const settingsKeys = {
  all: ["settings"] as const,
  detail: () => ["settings", "detail"] as const,
  sessions: () => ["settings", "sessions"] as const,
};

export const mentorKeys = {
  all: ["mentors"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["mentors", "list", params] as const,
  mentor: (id: string) => ["mentors", "mentor", id] as const,
  ownProfile: () => ["mentors", "own-profile"] as const,
  sentRequests: (
    params?: Record<string, string | number | boolean | undefined>,
  ) =>
    params
      ? (["mentors", "requests", "sent", params] as const)
      : (["mentors", "requests", "sent"] as const),
  incomingRequests: (
    params?: Record<string, string | number | boolean | undefined>,
  ) =>
    params
      ? (["mentors", "requests", "incoming", params] as const)
      : (["mentors", "requests", "incoming"] as const),
  /** The nav gate for the conditional Mentors entry (W4-E1). */
  connection: () => ["mentors", "connection"] as const,
};
