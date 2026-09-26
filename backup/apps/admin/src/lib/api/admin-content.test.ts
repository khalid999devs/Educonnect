import { describe, expect, it } from "vitest";

import { adminToolSchema, availableTransitions } from "./admin-content";

describe("availableTransitions", () => {
  it("mirrors the API content lifecycle state machine", () => {
    expect(availableTransitions("draft")).toEqual(["submit_for_review"]);
    expect(availableTransitions("in_review")).toEqual([
      "publish",
      "return_to_draft",
    ]);
    expect(availableTransitions("published")).toEqual([
      "archive",
      "return_to_draft",
    ]);
    expect(availableTransitions("archived")).toEqual(["return_to_draft"]);
  });
});

describe("adminToolSchema", () => {
  it("parses a curator tool row across states", () => {
    const tool = adminToolSchema.parse({
      id: "01JTOOL00000000000000000AA",
      name: "Concept Mapper",
      category: { slug: "study-planning", name: "Study planning" },
      purpose: "Map a reading.",
      selection_reason: "Keeps sources visible.",
      use_cases: ["Revision"],
      usage_guidance: "Paste notes.",
      limitations: "Cannot judge quality.",
      cost_note: "Free tier.",
      privacy_note: "No PII.",
      url: "https://tools.example.edu/x",
      provenance: "Reviewed.",
      state: "draft",
      last_reviewed_at: null,
      published_at: null,
      archived_at: null,
      version: 1,
      created_at: "2026-07-01T09:00:00Z",
      updated_at: "2026-07-01T09:00:00Z",
    });

    expect(tool.state).toBe("draft");
    expect(tool.use_cases).toEqual(["Revision"]);
  });
});
