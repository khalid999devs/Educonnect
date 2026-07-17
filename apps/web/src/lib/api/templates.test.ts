import { describe, expect, it } from "vitest";

import { templateCopySchema, templateSchema } from "./templates";

const TEMPLATE = {
  id: "01JTMPL0000000000000000000",
  title: "Assignment structure",
  category: { key: "academic-writing", name: "Academic writing" },
  summary: "A structured outline for high-quality assignments.",
  badge: "approved_free",
  integrity_note: "A scaffold to organize your own work, not to submit as-is.",
  provenance: "Curated by the EduConnect learning team.",
  latest_version: {
    number: 2,
    format: "markdown",
    body: "# Title\n\n## Introduction\n\n## Body\n\n## Conclusion",
  },
  last_reviewed_at: "2026-07-01T09:00:00Z",
  viewer_state: { saved: false, dismissed: false, active_copy_count: 1 },
};

const COPY = {
  id: "01JCOPY0000000000000000000",
  destination: "course",
  course: { id: "01JCOURSE000000000000000000", title: "Data Structures" },
  source: {
    template_id: "01JTMPL0000000000000000000",
    template_title: "Assignment structure",
    version_number: 2,
  },
  title: "PS2 write-up",
  format: "markdown",
  body: "# PS2\n\nMy own working notes.",
  version: 1,
  archived_at: null,
  created_at: "2026-07-16T09:00:00Z",
  updated_at: "2026-07-16T09:00:00Z",
};

describe("template schemas", () => {
  it("parses a template with its latest version and approved-free badge", () => {
    const template = templateSchema.parse(TEMPLATE);

    expect(template.badge).toBe("approved_free");
    expect(template.latest_version?.number).toBe(2);
    expect(template.viewer_state.active_copy_count).toBe(1);
  });

  it("parses a template with no published version", () => {
    const template = templateSchema.parse({
      ...TEMPLATE,
      latest_version: null,
    });

    expect(template.latest_version).toBeNull();
  });

  it("rejects a non-approved badge (no Premium in the contract)", () => {
    expect(() =>
      templateSchema.parse({ ...TEMPLATE, badge: "premium" }),
    ).toThrow();
  });

  it("parses a course-destination copy pinned to its source version", () => {
    const copy = templateCopySchema.parse(COPY);

    expect(copy.destination).toBe("course");
    expect(copy.source.version_number).toBe(2);
    expect(copy.archived_at).toBeNull();
  });

  it("parses a dashboard copy with a detached source", () => {
    const copy = templateCopySchema.parse({
      ...COPY,
      destination: "dashboard",
      course: null,
      source: {
        template_id: null,
        template_title: null,
        version_number: null,
      },
    });

    expect(copy.course).toBeNull();
    expect(copy.source.template_id).toBeNull();
  });
});
