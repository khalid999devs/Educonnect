import { apiFetch, toQueryString } from "./http";
import { parsePage, parseResource, type Page } from "./pagination";
import { z } from "zod";

export const ACCOUNT_STATUSES = ["active", "suspended"] as const;
export const ROLE_KEYS = [
  "student",
  "mentor",
  "moderator",
  "admin",
  "super_admin",
] as const;

export const adminUserSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string(),
  email_verified: z.boolean(),
  status: z.enum(ACCOUNT_STATUSES),
  suspended_at: z.string().nullable(),
  roles: z.array(z.string()),
  last_login_at: z.string().nullable(),
  created_at: z.string(),
});

export type AdminUser = z.infer<typeof adminUserSchema>;

export type UserFilters = {
  search?: string;
  role?: string;
  status?: string;
  cursor?: string;
};

export async function listUsers(
  filters: UserFilters,
): Promise<Page<AdminUser>> {
  const query = toQueryString({
    search: filters.search || undefined,
    role: filters.role || undefined,
    status: filters.status || undefined,
    cursor: filters.cursor,
  });

  return parsePage(
    await apiFetch(`/api/v1/admin/users${query}`),
    adminUserSchema,
  );
}

export async function getUser(id: string): Promise<AdminUser> {
  return parseResource(
    await apiFetch(`/api/v1/admin/users/${id}`),
    adminUserSchema,
  );
}

export async function suspendUser(
  id: string,
  reason: string,
): Promise<AdminUser> {
  return parseResource(
    await apiFetch(`/api/v1/admin/users/${id}/suspension`, {
      method: "POST",
      body: { reason },
    }),
    adminUserSchema,
  );
}

export async function reactivateUser(
  id: string,
  reason: string,
): Promise<AdminUser> {
  return parseResource(
    await apiFetch(`/api/v1/admin/users/${id}/reactivation`, {
      method: "POST",
      body: { reason },
    }),
    adminUserSchema,
  );
}

export async function changeUserRoles(
  id: string,
  roles: string[],
  reason: string,
): Promise<AdminUser> {
  return parseResource(
    await apiFetch(`/api/v1/admin/users/${id}/roles`, {
      method: "PUT",
      body: { roles, reason },
    }),
    adminUserSchema,
  );
}
