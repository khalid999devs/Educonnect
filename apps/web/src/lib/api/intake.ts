import { apiFetch } from "./http";

/** Quick Intake link capture (https-only per the API contract). */
export async function createLinkIntake(
  url: string,
  context?: string,
): Promise<void> {
  await apiFetch("/api/v1/intake/links", {
    method: "POST",
    body: { url, ...(context ? { context } : {}) },
  });
}
