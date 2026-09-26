import type { UserFilters } from "./api/admin-users";
import type { MentorFilters } from "./api/admin-mentors";

/** Central registry of TanStack Query keys for the console (ADR-0022 pattern). */

export const userKeys = {
  all: ["admin", "users"] as const,
  list: (filters: UserFilters) => [...userKeys.all, "list", filters] as const,
  detail: (id: string) => [...userKeys.all, "detail", id] as const,
};

export const roleKeys = {
  all: ["admin", "roles"] as const,
  list: () => [...roleKeys.all, "list"] as const,
};

export const mentorKeys = {
  all: ["admin", "mentors"] as const,
  list: (filters: MentorFilters) =>
    [...mentorKeys.all, "list", filters] as const,
};

export const reportKeys = {
  all: ["admin", "reports"] as const,
  list: (status: string) => [...reportKeys.all, "list", status] as const,
};

export const auditKeys = {
  all: ["admin", "audit"] as const,
  list: (action: string) => [...auditKeys.all, "list", action] as const,
};

export const contentKeys = {
  all: ["admin", "content"] as const,
  categories: () => [...contentKeys.all, "categories"] as const,
  type: (type: string) => [...contentKeys.all, type] as const,
  list: (type: string, state: string) =>
    [...contentKeys.type(type), "list", state] as const,
};

export const communityMgmtKeys = {
  all: ["admin", "communities"] as const,
  list: (visibility: string) =>
    [...communityMgmtKeys.all, "list", visibility] as const,
};

export const analyticsKeys = {
  overview: ["admin", "analytics", "overview"] as const,
  telemetry: ["admin", "analytics", "telemetry"] as const,
};
