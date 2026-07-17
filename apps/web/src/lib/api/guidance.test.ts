import { describe, expect, it } from "vitest";

import {
  guidanceBundleSchema,
  promptSchema,
  toolSchema,
  workflowSchema,
} from "./guidance";

const TOOL = {
  id: "01JTOOL0000000000000000000",
  name: "Concept Mapper",
  category: { key: "study-planning", name: "Study planning" },
  purpose: "Turn a dense reading into a labelled concept map.",
  selection_reason: "Chosen because it keeps sources visible and cited.",
  use_cases: ["Exam revision", "Literature review"],
  usage_guidance: "Paste your notes and let it group by theme.",
  limitations: "It cannot judge source quality for you.",
  cost_note: "Free tier is enough for coursework.",
  privacy_note: "Do not paste personally identifying data.",
  url: "https://tools.example.edu/concept-mapper",
  provenance: "Reviewed against the provider documentation.",
  last_reviewed_at: "2026-07-01T09:00:00Z",
  viewer_state: { saved: false, dismissed: false },
};

const PROMPT = {
  id: "01JPROMPT00000000000000000",
  title: "Explain like a study partner",
  category: { key: "study-planning", name: "Study planning" },
  purpose: "Get a plain-language explanation you can verify.",
  template_body: "Explain {{concept}} at a first-year level with one example.",
  placeholders: ["concept"],
  expected_output: "A short explanation plus one worked example.",
  integrity_note: "Use it to understand, then write the answer yourself.",
  provenance: "Authored by the EduConnect learning team.",
  related_tools: [
    {
      id: "01JTOOL0000000000000000000",
      name: "Concept Mapper",
      url: "https://tools.example.edu/concept-mapper",
    },
  ],
  last_reviewed_at: "2026-07-01T09:00:00Z",
  viewer_state: { saved: true, dismissed: false, copy_count: 3 },
};

const WORKFLOW = {
  id: "01JWORKFLOW0000000000000000",
  title: "From reading to revision notes",
  category: { key: "study-planning", name: "Study planning" },
  goal: "Convert a chapter into revision notes with integrity.",
  expected_outcome: "A set of notes in your own words with citations.",
  integrity_note: "Every step keeps the original source attributed.",
  provenance: "Curated by the EduConnect learning team.",
  steps: [
    {
      number: 1,
      title: "Read and highlight",
      instruction: "Skim, then mark the load-bearing claims.",
      destination_action: "save_resource",
      tool: null,
      prompt: null,
      template: null,
    },
    {
      number: 2,
      title: "Summarize in your words",
      instruction: "Use the prompt to draft, then rewrite it yourself.",
      destination_action: "use_prompt",
      tool: null,
      prompt: { id: "01JPROMPT00000000000000000", title: "Explain" },
      template: null,
    },
  ],
  last_reviewed_at: "2026-07-01T09:00:00Z",
  viewer_state: { saved: false, dismissed: false },
};

describe("guidance schemas", () => {
  it("parses a tool with its full rationale block", () => {
    const tool = toolSchema.parse(TOOL);

    expect(tool.use_cases).toHaveLength(2);
    expect(tool.viewer_state.saved).toBe(false);
  });

  it("parses a prompt with placeholders, related tools, and copy count", () => {
    const prompt = promptSchema.parse(PROMPT);

    expect(prompt.placeholders).toEqual(["concept"]);
    expect(prompt.related_tools[0]?.name).toBe("Concept Mapper");
    expect(prompt.viewer_state.copy_count).toBe(3);
  });

  it("parses a workflow with ordered steps and destination actions", () => {
    const workflow = workflowSchema.parse(WORKFLOW);

    expect(workflow.steps).toHaveLength(2);
    expect(workflow.steps[1]?.destination_action).toBe("use_prompt");
    expect(workflow.steps[1]?.prompt?.title).toBe("Explain");
  });

  it("rejects an unknown destination action", () => {
    expect(() =>
      workflowSchema.parse({
        ...WORKFLOW,
        steps: [{ ...WORKFLOW.steps[0], destination_action: "delete_course" }],
      }),
    ).toThrow();
  });

  it("parses a full guidance bundle across all four kinds", () => {
    const bundle = guidanceBundleSchema.parse({
      category: {
        key: "study-planning",
        name: "Study planning",
        description: "Plan and revise effectively.",
      },
      tools: [TOOL],
      prompts: [PROMPT],
      workflows: [WORKFLOW],
      templates: [],
    });

    expect(bundle.category.name).toBe("Study planning");
    expect(bundle.tools).toHaveLength(1);
    expect(bundle.prompts).toHaveLength(1);
    expect(bundle.workflows).toHaveLength(1);
  });
});
