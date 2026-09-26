import { z } from "zod";

import { apiFetch, envelopeData } from "./http";

/**
 * Settings surface (openapi.yaml Settings, Session).
 *
 * Deliberately narrow: the shipped scope is account name, the seven existing
 * academic profile columns, a course-preference summary, and the read-only
 * session list. Persisted timezone, persisted theme, notification
 * preferences, change-password-while-signed-in, and delete/export account all
 * require migrations and are out of scope, so no client function exists for
 * them.
 */

export const settingsAccountSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string(),
  email_verified: z.boolean(),
  primary_role: z.string().nullable(),
  created_at: z.string().nullable(),
});

export const settingsProfileSchema = z.object({
  institution_name: z.string().nullable(),
  institution_country_code: z.string().nullable(),
  department: z.string().nullable(),
  degree: z.string().nullable(),
  major: z.string().nullable(),
  year_label: z.string().nullable(),
  term_label: z.string().nullable(),
});

export const coursePreferencesSchema = z.object({
  total: z.number().int().min(0),
  active: z.number().int().min(0),
  archived: z.number().int().min(0),
  unfiled_resources: z.number().int().min(0),
});

export const settingsSchema = z.object({
  account: settingsAccountSchema,
  profile: settingsProfileSchema,
  course_preferences: coursePreferencesSchema,
  onboarding_completed: z.boolean(),
});

export type Settings = z.infer<typeof settingsSchema>;
export type SettingsProfile = z.infer<typeof settingsProfileSchema>;

/**
 * `id` is a one-way digest, never the session identifier: that value is the
 * bearer credential for the session. It exists only as a React key and must
 * never be rendered.
 */
export const sessionSummarySchema = z.object({
  id: z.string(),
  ip_address: z.string().nullable(),
  user_agent: z.string().nullable(),
  last_activity: z.string(),
  is_current: z.boolean(),
});

export type SessionSummary = z.infer<typeof sessionSummarySchema>;

const sessionListSchema = z.array(sessionSummarySchema);

export async function getSettings(): Promise<Settings> {
  return settingsSchema.parse(envelopeData(await apiFetch("/api/v1/settings")));
}

export async function updateAccount(input: {
  name: string;
}): Promise<Settings> {
  return settingsSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/settings/account", {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

/** Every profile field is `present` server-side, so all seven always ship. */
export async function updateProfile(input: SettingsProfile): Promise<Settings> {
  return settingsSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/settings/profile", {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

export async function listSessions(): Promise<SessionSummary[]> {
  return sessionListSchema.parse(
    envelopeData(await apiFetch("/api/v1/settings/sessions")),
  );
}

/**
 * Sign out everywhere. The backend invalidates the calling session too, so
 * the caller must clear the session context and navigate away.
 */
export async function logoutAllSessions(password: string): Promise<void> {
  await apiFetch("/api/v1/auth/logout-all", {
    method: "POST",
    body: { password },
  });
}
