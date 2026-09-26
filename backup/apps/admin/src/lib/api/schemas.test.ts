import { describe, expect, it } from "vitest";

import { adminSessionSchema } from "./schemas";

describe("adminSessionSchema", () => {
  it("parses a well-formed admin session envelope", () => {
    const session = adminSessionSchema.parse({
      user: {
        id: "01JADMIN0000000000000000AA",
        name: "Ada Admin",
        email: "admin@example.com",
        email_verified: true,
        primary_role: "admin",
      },
      authorization: {
        roles: ["admin"],
        capabilities: ["admin.access", "users.manage"],
      },
    });

    expect(session.user.name).toBe("Ada Admin");
    expect(session.authorization.capabilities).toContain("admin.access");
  });

  it("rejects a payload missing the authorization block", () => {
    expect(() =>
      adminSessionSchema.parse({
        user: {
          id: "01JADMIN0000000000000000AA",
          name: "Ada Admin",
          email: "admin@example.com",
          email_verified: true,
          primary_role: "admin",
        },
      }),
    ).toThrow();
  });

  it("rejects a user without an id", () => {
    expect(() =>
      adminSessionSchema.parse({
        user: {
          id: "",
          name: "Ada Admin",
          email: "admin@example.com",
          email_verified: true,
          primary_role: "admin",
        },
        authorization: { roles: [], capabilities: [] },
      }),
    ).toThrow();
  });
});
