import { describe, expect, it } from "vitest";

import {
  actionsForPurpose,
  DEFAULT_ACTIONS,
  isPurposeFilter,
  purposeDescriptor,
  PURPOSES,
  STUDY_ACTIONS,
} from "./purpose";

describe("purpose vocabulary", () => {
  it("covers exactly the four purposes the schema CHECK allows", () => {
    expect(PURPOSES.map((purpose) => purpose.value)).toEqual([
      "resource",
      "study",
      "research",
      "exam",
    ]);
  });

  it("never uses a banned decorative accent", () => {
    /* status-error means failure and status-warning means caution; neither
       may read as decoration (build brief 6.4). */
    for (const purpose of PURPOSES) {
      expect(purpose.accent).not.toBe("error");
      expect(purpose.accent).not.toBe("warning");
    }
  });

  it("offers only real generation kinds", () => {
    for (const purpose of PURPOSES) {
      for (const kind of purpose.actions) {
        expect(STUDY_ACTIONS[kind]).toBeDefined();
      }
    }
  });

  it("gives exam preparation the exam action", () => {
    expect(actionsForPurpose("exam")).toContain("exam_questions");
  });

  it("does not offer exam questions for a plain resource", () => {
    expect(actionsForPurpose("resource")).not.toContain("exam_questions");
  });

  it("treats a null purpose as a real value with a useful default", () => {
    /* Rows captured before purposes existed read back as null, which is not
       a synonym for "resource" and must not be defaulted away. */
    expect(purposeDescriptor(null)).toBeNull();
    expect(actionsForPurpose(null)).toEqual(DEFAULT_ACTIONS);
    expect(actionsForPurpose(undefined)).toEqual(DEFAULT_ACTIONS);
  });
});

describe("isPurposeFilter", () => {
  it("accepts the four purposes plus the sentinels", () => {
    for (const value of [
      "all",
      "none",
      "resource",
      "study",
      "research",
      "exam",
    ]) {
      expect(isPurposeFilter(value)).toBe(true);
    }
  });

  it("rejects anything else, so a tampered URL falls back cleanly", () => {
    expect(isPurposeFilter("")).toBe(false);
    expect(isPurposeFilter("everything")).toBe(false);
    expect(isPurposeFilter("__proto__")).toBe(false);
  });
});
