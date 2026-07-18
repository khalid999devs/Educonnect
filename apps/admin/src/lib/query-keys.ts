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
