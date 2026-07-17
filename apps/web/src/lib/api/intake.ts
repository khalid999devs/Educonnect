import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/** Exact runtime shapes of the Smart Intake contract (openapi.yaml). Nothing
 * is created until the user confirms reviewed suggestions; every suggestion
 * carries confidence, a plain-language reason, and a schema version. */

const isoDateTime = z.string();

export const intakeStateSchema = z.enum([
  "uploaded_or_linked",
  "queued",
  "extracting",
  "extracted",
  "organizing",
  "awaiting_review",
  "confirmed",
  "saved",
  "failed_retryable",
  "failed_final",
  "cancelled",
]);

export type IntakeState = z.infer<typeof intakeStateSchema>;

export const intakeFailureCodeSchema = z.enum([
  "unsafe_url",
  "link_fetch_failed",
  "link_http_client_error",
  "link_http_server_error",
  "content_too_large",
  "unsupported_content_type",
  "file_unavailable",
  "extraction_failed",
  "attempts_exhausted",
  "classification_failed",
]);

export type IntakeFailureCode = z.infer<typeof intakeFailureCodeSchema>;

export const intakeEventSchema = z.object({
  event: z.string(),
  from_state: intakeStateSchema.nullable(),
  to_state: intakeStateSchema.nullable(),
  detail: z.string().nullable(),
  occurred_at: isoDateTime.nullable(),
});

export const intakeItemSchema = z.object({
  id: z.string(),
  source: z.object({
    type: z.enum(["file", "link"]),
    url: z.string().nullable(),
    resource: z.object({ id: z.string(), title: z.string() }).nullable(),
  }),
  context: z.string().nullable(),
  state: intakeStateSchema,
  failure_code: intakeFailureCodeSchema.nullable(),
  attempts: z.number().int(),
  extraction: z
    .object({ characters: z.number().int(), content_type: z.string() })
    .nullable(),
  classification: z
    .object({
      provider: z.string(),
      model: z.string(),
      schema_version: z.string(),
      latency_ms: z.number().int(),
    })
    .nullable(),
  events: z.array(intakeEventSchema),
  queued_at: isoDateTime.nullable(),
  started_at: isoDateTime.nullable(),
  finished_at: isoDateTime.nullable(),
  cancelled_at: isoDateTime.nullable(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type IntakeItem = z.infer<typeof intakeItemSchema>;

export const intakeSuggestionSchema = z.object({
  id: z.string(),
  kind: z.enum(["task", "resource"]),
  proposal: z.object({
    title: z.string().nullable(),
    description: z.string().nullable(),
    due_at: z.string().nullable(),
    course_id: z.string().nullable(),
    url: z.string().nullable(),
  }),
  confidence: z.number().min(0).max(1),
  reason: z.string(),
  schema_version: z.string(),
  status: z.enum(["proposed", "dismissed", "applied"]),
  created_task_id: z.string().nullable(),
  created_resource_id: z.string().nullable(),
  created_at: isoDateTime.nullable(),
});

export type IntakeSuggestion = z.infer<typeof intakeSuggestionSchema>;

const cursorPagination = z.object({
  next_cursor: z.string().nullable(),
  previous_cursor: z.string().nullable(),
  per_page: z.number().int(),
});

const intakeItemCollectionSchema = z.object({
  data: z.array(intakeItemSchema),
  meta: z.object({ pagination: cursorPagination }),
});

const suggestionCollectionSchema = z.object({
  data: z.array(intakeSuggestionSchema),
});

export async function listIntakeItems(
  params: { state?: IntakeState; perPage?: number; cursor?: string } = {},
): Promise<z.infer<typeof intakeItemCollectionSchema>> {
  const query = toQueryString({
    state: params.state,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return intakeItemCollectionSchema.parse(
    await apiFetch(`/api/v1/intake${query}`),
  );
}

/** Quick Intake link capture (https-only per the API contract). */
export async function createLinkIntake(
  url: string,
  context?: string,
): Promise<IntakeItem> {
  return intakeItemSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/intake/links", {
        method: "POST",
        body: { url, ...(context ? { context } : {}) },
      }),
    ),
  );
}

export async function createFileIntake(
  resourceId: string,
  context?: string,
): Promise<IntakeItem> {
  return intakeItemSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/intake/files", {
        method: "POST",
        body: { resource_id: resourceId, ...(context ? { context } : {}) },
      }),
    ),
  );
}

export async function getIntakeItem(itemId: string): Promise<IntakeItem> {
  return intakeItemSchema.parse(
    envelopeData(await apiFetch(`/api/v1/intake/${itemId}`)),
  );
}

export async function cancelIntakeItem(itemId: string): Promise<IntakeItem> {
  return intakeItemSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/intake/${itemId}/cancel`, { method: "POST" }),
    ),
  );
}

export async function retryIntakeItem(itemId: string): Promise<IntakeItem> {
  return intakeItemSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/intake/${itemId}/retry`, { method: "POST" }),
    ),
  );
}

export async function listIntakeSuggestions(
  itemId: string,
): Promise<IntakeSuggestion[]> {
  return suggestionCollectionSchema.parse(
    await apiFetch(`/api/v1/intake/${itemId}/suggestions`),
  ).data;
}

export type ConfirmDecision = {
  id: string;
  action: "apply" | "dismiss";
  overrides?: {
    title?: string | null;
    description?: string | null;
    due_at?: string | null;
    course_id?: string | null;
    url?: string | null;
  };
};

export async function confirmIntake(
  itemId: string,
  decisions: ConfirmDecision[],
): Promise<IntakeItem> {
  return intakeItemSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/intake/${itemId}/confirmation`, {
        method: "POST",
        body: { decisions },
      }),
    ),
  );
}

/** Ordered pipeline states for the intake stepper. */
export const INTAKE_PIPELINE: IntakeState[] = [
  "queued",
  "extracting",
  "extracted",
  "organizing",
  "awaiting_review",
  "saved",
];

const TERMINAL_STATES: IntakeState[] = ["saved", "failed_final", "cancelled"];

export function isTerminal(state: IntakeState): boolean {
  return TERMINAL_STATES.includes(state);
}

export function isProcessing(state: IntakeState): boolean {
  return (
    state === "uploaded_or_linked" ||
    state === "queued" ||
    state === "extracting" ||
    state === "extracted" ||
    state === "organizing"
  );
}
