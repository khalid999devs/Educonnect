import { describe, expect, it } from "vitest";

import {
  addDays,
  durationMinutes,
  isOnLocalDate,
  localDateOf,
  localTimeInputValue,
  minutesIntoDay,
  mondayOf,
  toApiInstant,
  weekDates,
} from "./time";

describe("planner time math", () => {
  it("derives the local date across the day boundary by timezone", () => {
    /* 22:30 UTC is already the next day in Dhaka (+06). */
    const instant = new Date("2026-07-17T22:30:00Z");

    expect(localDateOf(instant, "UTC")).toBe("2026-07-17");
    expect(localDateOf(instant, "Asia/Dhaka")).toBe("2026-07-18");
  });

  it("adds days across month boundaries on the calendar string", () => {
    expect(addDays("2026-07-31", 1)).toBe("2026-08-01");
    expect(addDays("2026-01-01", -1)).toBe("2025-12-31");
  });

  it("finds Monday for any weekday including Sunday", () => {
    /* 2026-07-17 is a Friday. */
    expect(mondayOf("2026-07-17")).toBe("2026-07-13");
    /* 2026-07-19 is a Sunday; its week still starts the prior Monday. */
    expect(mondayOf("2026-07-19")).toBe("2026-07-13");
    /* 2026-07-13 is itself a Monday. */
    expect(mondayOf("2026-07-13")).toBe("2026-07-13");
  });

  it("returns the seven dates of a week", () => {
    expect(weekDates("2026-07-13")).toEqual([
      "2026-07-13",
      "2026-07-14",
      "2026-07-15",
      "2026-07-16",
      "2026-07-17",
      "2026-07-18",
      "2026-07-19",
    ]);
  });

  it("computes minutes into the local day for grid placement", () => {
    const instant = "2026-07-17T04:30:00Z";

    /* 04:30 UTC = 10:30 Dhaka = 630 minutes. */
    expect(minutesIntoDay(instant, "Asia/Dhaka")).toBe(630);
    expect(minutesIntoDay(instant, "UTC")).toBe(270);
  });

  it("matches an instant to a local date only in the right zone", () => {
    const instant = "2026-07-17T22:30:00Z";

    expect(isOnLocalDate(instant, "2026-07-18", "Asia/Dhaka")).toBe(true);
    expect(isOnLocalDate(instant, "2026-07-17", "Asia/Dhaka")).toBe(false);
    expect(isOnLocalDate(instant, "2026-07-17", "UTC")).toBe(true);
  });

  it("formats a 24h time input value from an instant", () => {
    const instant = "2026-07-17T04:05:00Z";

    /* 04:05 UTC = 10:05 Dhaka. */
    expect(localTimeInputValue(instant, "Asia/Dhaka")).toBe("10:05");
    expect(localTimeInputValue(instant, "UTC")).toBe("04:05");
  });

  it("computes non-negative durations", () => {
    expect(
      durationMinutes("2026-07-17T09:00:00Z", "2026-07-17T09:50:00Z"),
    ).toBe(50);
    /* Reversed inputs never go negative. */
    expect(
      durationMinutes("2026-07-17T09:50:00Z", "2026-07-17T09:00:00Z"),
    ).toBe(0);
  });

  it("emits second-precision UTC instants the API accepts", () => {
    const instant = new Date("2026-07-17T09:30:45.123Z");

    expect(toApiInstant(instant)).toBe("2026-07-17T09:30:45Z");
    expect(toApiInstant(instant)).not.toContain(".");
  });
});
