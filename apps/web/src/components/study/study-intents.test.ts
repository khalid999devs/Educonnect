import { describe, expect, it } from "vitest";

import {
  findStudyIntent,
  orderedKindsForIntent,
  STUDY_INTENTS,
  STUDY_KIND_ORDER,
  STUDY_KINDS,
} from "./study-intents";

describe("study intents", () => {
  it("offers exactly the three entry points the section promises", () => {
    expect(STUDY_INTENTS.map((intent) => intent.value)).toEqual([
      "study",
      "learn",
      "exam",
    ]);
  });

  it("leads exam preparation with exam questions", () => {
    expect(orderedKindsForIntent("exam")[0]).toBe("exam_questions");
  });

  it("keeps every kind reachable from every intent, without duplicates", () => {
    for (const intent of STUDY_INTENTS) {
      const kinds = orderedKindsForIntent(intent.value);

      expect([...kinds].sort()).toEqual([...STUDY_KIND_ORDER].sort());
      expect(new Set(kinds).size).toBe(kinds.length);
    }
  });

  it("describes every artifact kind the API can return", () => {
    for (const kind of STUDY_KIND_ORDER) {
      expect(STUDY_KINDS[kind].label).not.toBe("");
      expect(STUDY_KINDS[kind].description).not.toBe("");
      expect(STUDY_KINDS[kind].cta).not.toBe("");
    }
  });

  it("never promises a grade or an outcome in its own copy", () => {
    const copy = [
      ...STUDY_INTENTS.flatMap((intent) => [
        intent.tagline,
        intent.description,
      ]),
      ...STUDY_KIND_ORDER.map((kind) => STUDY_KINDS[kind].description),
    ].join(" ");

    expect(copy).not.toMatch(/guarantee|ace |top marks|will pass|best grade/i);
  });

  it("resolves a config for every intent value", () => {
    for (const intent of STUDY_INTENTS) {
      expect(findStudyIntent(intent.value).value).toBe(intent.value);
    }
  });
});
