import { describe, expect, it } from "vitest";

import { scenarioSearchSchema, toolCategorySchema } from "./tools";

/** Contract mirrors of `ToolCategory` and `ScenarioSearch` in openapi.yaml. */

const CATEGORY = {
  id: "01JZ7N6H1C8J3R4S5T6V7W8XA0",
  key: "exam-prep",
  name: "Exam prep",
  description: "Tools for the last stretch before an exam.",
  sort_order: 3,
};

describe("toolCategorySchema", () => {
  it("parses a curated category", () => {
    expect(toolCategorySchema.parse(CATEGORY).key).toBe("exam-prep");
  });

  it("accepts a null description", () => {
    expect(
      toolCategorySchema.parse({ ...CATEGORY, description: null }).description,
    ).toBeNull();
  });

  it("rejects a missing sort_order", () => {
    const { sort_order: _omitted, ...withoutSort } = CATEGORY;

    expect(() => toolCategorySchema.parse(withoutSort)).toThrow();
  });

  it("rejects a non-integer sort_order", () => {
    expect(() =>
      toolCategorySchema.parse({ ...CATEGORY, sort_order: 1.5 }),
    ).toThrow();
  });
});

describe("scenarioSearchSchema", () => {
  const RANKING = {
    results: [],
    ai_ranked: false,
    cached: true,
    provider: "deterministic",
    model: "none",
    disclaimer: "Ranking reorders a curated list; it never invents a tool.",
  };

  it("parses a degraded, unranked response as a normal success", () => {
    const parsed = scenarioSearchSchema.parse(RANKING);

    expect(parsed.ai_ranked).toBe(false);
    expect(parsed.results).toHaveLength(0);
  });

  it("rejects a response missing the honesty flag", () => {
    const { ai_ranked: _omitted, ...withoutFlag } = RANKING;

    expect(() => scenarioSearchSchema.parse(withoutFlag)).toThrow();
  });

  it("rejects a results member that is not an array", () => {
    expect(() =>
      scenarioSearchSchema.parse({ ...RANKING, results: null }),
    ).toThrow();
  });
});
