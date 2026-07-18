import { describe, expect, it } from "vitest";

import { adminUserSchema } from "./admin-users";

describe("adminUserSchema", () => {
  it("parses a well-formed admin user row", () => {
    const user = adminUserSchema.parse({
      id: "01JADMIN0000000000000000AA",
      name: "Sam Student",
      email: "sam@example.com",
      email_verified: true,
      status: "active",
      suspended_at: null,
      roles: ["student"],
      last_login_at: "2026-07-18T09:00:00Z",
      created_at: "2026-07-01T09:00:00Z",
    });

    expect(user.status).toBe("active");
    expect(user.roles).toEqual(["student"]);
  });

  it("rejects an unknown status", () => {
    expect(() =>
      adminUserSchema.parse({
        id: "01JADMIN0000000000000000AA",
        name: "Sam",
        email: "sam@example.com",
        email_verified: true,
        status: "archived",
        suspended_at: null,
        roles: [],
        last_login_at: null,
        created_at: "2026-07-01T09:00:00Z",
      }),
    ).toThrow();
  });
});
