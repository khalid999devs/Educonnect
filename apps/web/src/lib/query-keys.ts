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
