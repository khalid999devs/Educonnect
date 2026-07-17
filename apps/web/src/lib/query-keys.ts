/** Query-key families for the authenticated app (ADR-0022). Mutations
 * invalidate whole families so every window/list stays truthful. */

export const plannerKeys = {
  all: ["planner"] as const,
  agenda: (timezone: string, date: string) =>
    ["planner", "agenda", timezone, date] as const,
  weekly: (timezone: string, weekStart: string) =>
    ["planner", "weekly", timezone, weekStart] as const,
};

export const resourceKeys = {
  all: ["resources"] as const,
  list: (params: Record<string, string | number | boolean | undefined>) =>
    ["resources", "list", params] as const,
};

export const courseKeys = {
  all: ["courses"] as const,
  list: () => ["courses", "list"] as const,
};

export const guidanceKeys = {
  all: ["guidance"] as const,
  categories: () => ["guidance", "categories"] as const,
  bundle: (category: string) => ["guidance", "bundle", category] as const,
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
};

export const brainKeys = {
  all: ["brain"] as const,
  search: (params: Record<string, string | number | boolean | undefined>) =>
    ["brain", "search", params] as const,
  item: (id: string) => ["brain", "item", id] as const,
  collections: () => ["brain", "collections"] as const,
};

export const researchKeys = {
  all: ["research"] as const,
  list: () => ["research", "list"] as const,
  topic: (id: string) => ["research", "topic", id] as const,
};
