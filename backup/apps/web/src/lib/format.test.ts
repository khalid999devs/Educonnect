import { describe, expect, it } from "vitest";

import { formatRelativeTime } from "./format";

describe("formatRelativeTime", () => {
  it("returns an empty string for invalid input", () => {
    expect(formatRelativeTime("not-a-date")).toBe("");
  });

  it("formats a time a couple of hours in the past", () => {
    const twoHoursAgo = new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString();
    expect(formatRelativeTime(twoHoursAgo)).toMatch(/hour/);
  });

  it("formats a time a few days in the past", () => {
    const threeDaysAgo = new Date(
      Date.now() - 3 * 24 * 60 * 60 * 1000,
    ).toISOString();
    expect(formatRelativeTime(threeDaysAgo)).toMatch(/day/);
  });
});
