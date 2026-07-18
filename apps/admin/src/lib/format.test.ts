import { describe, expect, it } from "vitest";

import { formatDateTime, humanizeKey } from "./format";

describe("formatDateTime", () => {
  it("returns an em dash for null or invalid input", () => {
    expect(formatDateTime(null)).toBe("—");
    expect(formatDateTime("not-a-date")).toBe("—");
  });

  it("formats a valid ISO timestamp", () => {
    expect(formatDateTime("2026-07-18T09:00:00Z")).not.toBe("—");
  });
});

describe("humanizeKey", () => {
  it("turns a namespaced key into a readable label", () => {
    expect(humanizeKey("users.suspend")).toBe("Users Suspend");
    expect(humanizeKey("super_admin")).toBe("Super Admin");
    expect(humanizeKey("authorization.roles-view")).toBe(
      "Authorization Roles View",
    );
  });
});
