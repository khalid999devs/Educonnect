import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/** Exact runtime shapes of the Study contract (openapi.yaml).
 *
 * Two invariants this module exists to preserve on the client:
 *
 * 1. A non-ready artifact never carries a payload, and a failed one never
 *    does either. `payload` is nullable and every renderer must branch on
 *    `status` first, so a failure is reported honestly and can never be
 *    dressed up as generated study material.
 * 2. Chat history is client-held and bounded to 8 turns (ADR-0021 rejected
 *    conversation persistence). `boundStudyChatHistory` is the single place
 *    that bound is applied. */

const isoDateTime = z.string();

export const studyArtifactKindSchema = z.enum([
  "summary",
  "topic_explanation",
  "quick_learn",
  "exam_questions",
]);
export type StudyArtifactKind = z.infer<typeof studyArtifactKindSchema>;

export const studyArtifactStatusSchema = z.enum([
  "queued",
  "running",
  "ready",
  "failed",
]);
export type StudyArtifactStatus = z.infer<typeof studyArtifactStatusSchema>;

/** StudyOutputSchemaV1: summary, topic_explanation, and quick_learn. */
export const studyOutputPayloadSchema = z.object({
  title: z.string(),
  overview: z.string(),
  sections: z.array(z.object({ heading: z.string(), body: z.string() })),
  key_points: z.array(z.string()),
});
export type StudyOutputPayload = z.infer<typeof studyOutputPayloadSchema>;

/** ExamQuestionSchemaV1: exam_questions only.
 *
 * `options` is null for an open-response question and a list of two to six
 * distinct strings for a multiple-choice one - the server schema treats both
 * as legitimate, so a nullable list is the only shape that round-trips. When
 * options are present the server has already proved the answer is one of
 * them; a set where it was not is rejected whole rather than shipped with one
 * broken question. */
export const examQuestionSchema = z.object({
  prompt: z.string(),
  options: z.array(z.string()).nullable(),
  answer: z.string(),
  explanation: z.string(),
});
export type ExamQuestion = z.infer<typeof examQuestionSchema>;

export const examQuestionPayloadSchema = z.object({
  questions: z.array(examQuestionSchema),
});
export type ExamQuestionPayload = z.infer<typeof examQuestionPayloadSchema>;

export const studyArtifactSchema = z.object({
  id: z.string(),
  kind: studyArtifactKindSchema,
  status: studyArtifactStatusSchema,
  version: z.number().int().min(1),
  /** True while queued or running; the client polls until this is false. */
  is_pending: z.boolean(),
  /** Present only when status is ready. Shape depends on kind, so it stays
   * unknown here and is narrowed by the renderer via the parsers below. */
  payload: z.record(z.string(), z.unknown()).nullable(),
  failure_reason: z.string().nullable(),
  schema_version: z.string(),
  provider: z.string().nullable(),
  model: z.string().nullable(),
  latency_ms: z.number().int().nullable(),
  disclaimer: z.string(),
  created_at: isoDateTime.nullable(),
  updated_at: isoDateTime.nullable(),
});

export type StudyArtifact = z.infer<typeof studyArtifactSchema>;

const studyArtifactCollectionSchema = z.object({
  data: z.array(studyArtifactSchema),
  meta: z.object({
    summary: z.object({
      total: z.number().int(),
      queued: z.number().int(),
      running: z.number().int(),
      ready: z.number().int(),
      failed: z.number().int(),
    }),
    pagination: z.object({
      next_cursor: z.string().nullable(),
      previous_cursor: z.string().nullable(),
      per_page: z.number().int(),
    }),
  }),
});

export type StudyArtifactListParams = {
  itemId?: string;
  kind?: StudyArtifactKind;
  status?: StudyArtifactStatus;
  sort?: "created_at" | "-created_at" | "updated_at" | "-updated_at";
  perPage?: number;
  cursor?: string;
};

export async function listStudyArtifacts(
  params: StudyArtifactListParams = {},
): Promise<z.infer<typeof studyArtifactCollectionSchema>> {
  const query = toQueryString({
    item_id: params.itemId,
    kind: params.kind,
    status: params.status,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return studyArtifactCollectionSchema.parse(
    await apiFetch(`/api/v1/study/artifacts${query}`),
  );
}

export async function getStudyArtifact(
  artifactId: string,
): Promise<StudyArtifact> {
  return studyArtifactSchema.parse(
    envelopeData(await apiFetch(`/api/v1/study/artifacts/${artifactId}`)),
  );
}

/** Always 202. UNIQUE(knowledge_item_id, kind) makes the persisted artifact
 * both the cache and the dedupe key, so repeating a request for a queued,
 * running, or ready artifact returns that same artifact without re-billing a
 * provider. A failed artifact is re-queued in place with its version bumped. */
export async function requestStudyGeneration(
  itemId: string,
  kind: StudyArtifactKind,
): Promise<StudyArtifact> {
  return studyArtifactSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/study/${itemId}/generations`, {
        method: "POST",
        body: { kind },
      }),
    ),
  );
}

export const studyChatRoleSchema = z.enum(["user", "assistant"]);
export type StudyChatRole = z.infer<typeof studyChatRoleSchema>;

export const studyChatTurnSchema = z.object({
  role: studyChatRoleSchema,
  content: z.string().min(1).max(1000),
});
export type StudyChatTurn = z.infer<typeof studyChatTurnSchema>;

export const studyChatReplySchema = z.object({
  reply: z.string(),
  model: z.string(),
  disclaimer: z.string(),
});
export type StudyChatReply = z.infer<typeof studyChatReplySchema>;

const studyChatReplyEnvelopeSchema = z.object({
  study_chat: studyChatReplySchema,
});

/** The server's contract cap. History is client-held: there is no
 * conversation record, so the client is the only thing enforcing this
 * before the request goes out. */
export const MAX_STUDY_CHAT_HISTORY_TURNS = 8;
export const MAX_STUDY_CHAT_MESSAGE_CHARACTERS = 1000;

/** Keeps the most recent turns and drops the oldest. The `role` enum is
 * deliberately narrow: a client-supplied `system` turn would overwrite the
 * guardrails the server assembled, so it cannot be represented at all. */
export function boundStudyChatHistory(
  history: readonly StudyChatTurn[],
): StudyChatTurn[] {
  return history.slice(-MAX_STUDY_CHAT_HISTORY_TURNS).map((turn) => ({
    role: turn.role,
    content: turn.content.slice(0, MAX_STUDY_CHAT_MESSAGE_CHARACTERS),
  }));
}

export async function sendStudyChatMessage(
  itemId: string,
  message: string,
  history: readonly StudyChatTurn[] = [],
): Promise<StudyChatReply> {
  const bounded = boundStudyChatHistory(history);

  return studyChatReplyEnvelopeSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/study/${itemId}/chat`, {
        method: "POST",
        body: {
          message: message.slice(0, MAX_STUDY_CHAT_MESSAGE_CHARACTERS),
          ...(bounded.length > 0 ? { history: bounded } : {}),
        },
      }),
    ),
  ).study_chat;
}

/** Narrows a ready artifact's payload. Returns null rather than throwing:
 * an artifact whose payload does not match its kind must render as an
 * honest "unavailable", never as partially fabricated content. */
export function readStudyOutput(
  artifact: StudyArtifact,
): StudyOutputPayload | null {
  if (artifact.status !== "ready" || artifact.payload === null) {
    return null;
  }

  const parsed = studyOutputPayloadSchema.safeParse(artifact.payload);

  return parsed.success ? parsed.data : null;
}

export function readExamQuestions(
  artifact: StudyArtifact,
): ExamQuestionPayload | null {
  if (artifact.status !== "ready" || artifact.payload === null) {
    return null;
  }

  const parsed = examQuestionPayloadSchema.safeParse(artifact.payload);

  return parsed.success ? parsed.data : null;
}

export function isStudyArtifactPending(artifact: StudyArtifact): boolean {
  return artifact.status === "queued" || artifact.status === "running";
}
