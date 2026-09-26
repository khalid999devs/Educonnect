import { describe, expect, it } from "vitest";

import {
  INTAKE_PIPELINE,
  intakeItemSchema,
  intakeSuggestionSchema,
  isProcessing,
  isTerminal,
} from "./intake";

const ITEM = {
  id: "01JINTAKE00000000000000000",
  source: {
    type: "link",
    url: "https://example.edu/syllabus",
    resource: null,
  },
  context: "Course syllabus",
  state: "awaiting_review",
  failure_code: null,
  attempts: 1,
  extraction: { characters: 4200, content_type: "text/html" },
  classification: {
    provider: "rule_based",
    model: "v1",
    schema_version: "v1",
    latency_ms: 12,
  },
  events: [
    {
      event: "queued",
      from_state: "uploaded_or_linked",
      to_state: "queued",
      detail: "Queued for processing",
      occurred_at: "2026-07-18T09:00:00Z",
    },
  ],
  queued_at: "2026-07-18T09:00:00Z",
  started_at: "2026-07-18T09:00:05Z",
  finished_at: null,
  cancelled_at: null,
  created_at: "2026-07-18T09:00:00Z",
  updated_at: "2026-07-18T09:01:00Z",
};

const SUGGESTION = {
  id: "01JSUGGEST000000000000000",
  kind: "task",
  proposal: {
    title: "Read chapter 3",
    description: "Prepare for the quiz",
    due_at: "2026-07-25",
    course_id: "01JCOURSE000000000000000000",
    url: null,
  },
  confidence: 0.82,
  reason: "The syllabus lists a chapter-3 quiz next week.",
  schema_version: "v1",
  status: "proposed",
  created_task_id: null,
  created_resource_id: null,
  created_knowledge_item_id: null,
  created_at: null,
};

describe("intake schemas", () => {
  it("parses an intake item with extraction and classification evidence", () => {
    const item = intakeItemSchema.parse(ITEM);

    expect(item.extraction?.characters).toBe(4200);
    expect(item.classification?.provider).toBe("rule_based");
    expect(item.events).toHaveLength(1);
  });

  it("parses a task suggestion with confidence and a reason", () => {
    const suggestion = intakeSuggestionSchema.parse(SUGGESTION);

    expect(suggestion.kind).toBe("task");
    expect(suggestion.confidence).toBeCloseTo(0.82);
    expect(suggestion.proposal.due_at).toBe("2026-07-25");
  });

  it("rejects an out-of-range confidence", () => {
    expect(() =>
      intakeSuggestionSchema.parse({ ...SUGGESTION, confidence: 1.4 }),
    ).toThrow();
  });

  it("rejects an unknown intake state", () => {
    expect(() =>
      intakeItemSchema.parse({ ...ITEM, state: "teleported" }),
    ).toThrow();
  });
});

describe("intake state helpers", () => {
  it("classifies processing states", () => {
    expect(isProcessing("extracting")).toBe(true);
    expect(isProcessing("organizing")).toBe(true);
    expect(isProcessing("awaiting_review")).toBe(false);
    expect(isProcessing("saved")).toBe(false);
  });

  it("classifies terminal states", () => {
    expect(isTerminal("saved")).toBe(true);
    expect(isTerminal("failed_final")).toBe(true);
    expect(isTerminal("cancelled")).toBe(true);
    expect(isTerminal("awaiting_review")).toBe(false);
  });

  it("orders the pipeline from queued to saved", () => {
    expect(INTAKE_PIPELINE[0]).toBe("queued");
    expect(INTAKE_PIPELINE[INTAKE_PIPELINE.length - 1]).toBe("saved");
    expect(INTAKE_PIPELINE).toContain("awaiting_review");
  });
});
