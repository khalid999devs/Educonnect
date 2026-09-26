import { apiFetch, envelopeData } from "./http";
import { z } from "zod";

export const roleSchema = z.object({
  key: z.string(),
  name: z.string(),
  display_priority: z.number(),
  is_protected: z.boolean(),
  capabilities: z.array(z.string()),
});

export type Role = z.infer<typeof roleSchema>;

export async function listRoles(): Promise<Role[]> {
  return z
    .array(roleSchema)
    .parse(envelopeData(await apiFetch("/api/v1/admin/roles")));
}
