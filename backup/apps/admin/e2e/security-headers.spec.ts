import { expect, test } from "@playwright/test";

/**
 * The restricted admin console must ship hardened HTTP security response
 * headers on every route (Phase 28), and must never be framable. Uses
 * waitUntil "commit" so the assertion runs on the document response without
 * waiting for the session probe to settle.
 */
test.describe("security headers", () => {
  test("the console is served with hardened, unframable security headers", async ({
    page,
  }) => {
    const response = await page.goto("/", { waitUntil: "commit" });
    expect(response).not.toBeNull();

    const headers = response!.headers();
    const csp = headers["content-security-policy"] ?? "";

    expect(csp).toContain("default-src 'self'");
    expect(csp).toContain("object-src 'none'");
    expect(csp).toContain("frame-ancestors 'none'");
    expect(headers["x-content-type-options"]).toBe("nosniff");
    expect(headers["x-frame-options"]).toBe("DENY");
    expect(headers["referrer-policy"]).toBe("strict-origin-when-cross-origin");
    expect(headers["permissions-policy"]).toContain("camera=()");
  });
});
