import { apiFetch, envelopeData } from "./http";
import { adminSessionSchema, type AdminSession } from "./schemas";

function parseSession(payload: unknown): AdminSession {
  return adminSessionSchema.parse(envelopeData(payload));
}

/**
 * Start an admin session. The backend guard admits only verified users holding
 * the admin-access capability; anyone else receives an identical validation
 * error, so the console never distinguishes "not an admin" from "wrong
 * password".
 */
export async function adminLogin(input: {
  email: string;
  password: string;
}): Promise<AdminSession> {
  return parseSession(
    await apiFetch("/api/v1/admin/auth/login", {
      method: "POST",
      body: input,
    }),
  );
}

export async function adminLogout(): Promise<void> {
  await apiFetch("/api/v1/admin/auth/logout", { method: "POST" });
}

export async function currentAdmin(): Promise<AdminSession> {
  return parseSession(await apiFetch("/api/v1/admin/me"));
}
