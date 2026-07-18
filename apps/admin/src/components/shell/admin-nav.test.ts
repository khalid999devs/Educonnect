import { describe, expect, it } from "vitest";

import { navItemEnabled, type AdminNavItem } from "./admin-nav";

const item = (overrides: Partial<AdminNavItem>): AdminNavItem => ({
  label: "X",
  href: "/x",
  icon: (() => null) as unknown as AdminNavItem["icon"],
  available: true,
  ...overrides,
});

describe("navItemEnabled", () => {
  const canNothing = () => false;
  const can = (held: string[]) => (capability: string) =>
    held.includes(capability);

  it("disables a module that has not shipped, regardless of capability", () => {
    expect(navItemEnabled(item({ available: false }), () => true)).toBe(false);
  });

  it("enables an available module with no capability requirement", () => {
    expect(navItemEnabled(item({ capability: undefined }), canNothing)).toBe(
      true,
    );
  });

  it("requires the capability when one is set", () => {
    const nav = item({ capability: "users.suspend" });
    expect(navItemEnabled(nav, canNothing)).toBe(false);
    expect(navItemEnabled(nav, can(["users.suspend"]))).toBe(true);
  });

  it("treats a capability list as any-of", () => {
    const nav = item({
      capability: ["moderation.scoped", "moderation.global"],
    });
    expect(navItemEnabled(nav, can(["moderation.global"]))).toBe(true);
    expect(navItemEnabled(nav, can(["audit.view-all"]))).toBe(false);
  });
});
