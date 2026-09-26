/** Public marketing contact form. Unauthenticated; the API returns 204 on success. */

import { apiFetch } from "./http";

export type ContactTopic = "product" | "privacy" | "partnership" | "other";

export type ContactMessageInput = {
  name: string;
  email: string;
  topic: ContactTopic;
  message: string;
};

export async function submitContactMessage(
  input: ContactMessageInput,
): Promise<void> {
  await apiFetch("/api/v1/contact", { method: "POST", body: input });
}
