import { expect, test } from "@playwright/test";

import {
  API,
  adminSession,
  envelope,
  mockCsrf,
  mockJson,
  validationError,
} from "./support";

const ME = `${API}/api/v1/admin/me`;
const LOGIN = `${API}/api/v1/admin/auth/login`;
const LOGOUT = `${API}/api/v1/admin/auth/logout`;

test.describe("admin console foundation", () => {
  test("a guest cannot load the console and is sent to sign in", async ({
    page,
  }) => {
    await mockJson(
      page,
      ME,
      { error: { code: "AUTHENTICATION_REQUIRED" } },
      401,
    );

    await page.goto("/");

    await expect(page).toHaveURL(/\/login/);
    await expect(
      page.getByRole("heading", { name: "Sign in to the console" }),
    ).toBeVisible();
    /* The console shell and its data never render for a guest. */
    await expect(
      page.getByRole("heading", { name: "Console overview" }),
    ).toHaveCount(0);
  });

  test("invalid or non-admin credentials show an error and stay on sign in", async ({
    page,
  }) => {
    await mockJson(
      page,
      ME,
      { error: { code: "AUTHENTICATION_REQUIRED" } },
      401,
    );
    await mockCsrf(page);
    await mockJson(
      page,
      LOGIN,
      validationError("email", "The provided credentials are incorrect."),
      422,
    );

    await page.goto("/login");
    await page.getByLabel("Email").fill("nope@example.com");
    await page.getByLabel("Password").fill("wrong-password");
    await page.getByRole("button", { name: "Sign in to the console" }).click();

    await expect(
      page.getByText("The provided credentials are incorrect."),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test("a verified admin signs in and reaches the console overview", async ({
    page,
  }) => {
    await mockJson(
      page,
      ME,
      { error: { code: "AUTHENTICATION_REQUIRED" } },
      401,
    );
    await mockCsrf(page);
    await mockJson(page, LOGIN, envelope(adminSession()));

    await page.goto("/login");
    await page.getByLabel("Email").fill("admin@example.com");
    await page.getByLabel("Password").fill("secret123");
    await page.getByRole("button", { name: "Sign in to the console" }).click();

    await expect(
      page.getByRole("heading", { name: "Console overview" }),
    ).toBeVisible();
    await expect(page.getByText("Signed in as Ada Admin.")).toBeVisible();
    /* Real capabilities from the session drive the overview, not fabricated data. */
    await expect(
      page.getByRole("heading", { name: "Your access" }),
    ).toBeVisible();
  });

  test("an already-authenticated admin is forwarded past the sign-in page", async ({
    page,
  }) => {
    await mockJson(page, ME, envelope(adminSession()));

    await page.goto("/login");

    await expect(page).toHaveURL(/\/$|\/(\?.*)?$/);
    await expect(
      page.getByRole("heading", { name: "Console overview" }),
    ).toBeVisible();
  });

  test("signing out clears the session and returns to sign in", async ({
    page,
  }) => {
    await mockJson(page, ME, envelope(adminSession()));
    await mockCsrf(page);
    await mockJson(page, LOGOUT, envelope(null));

    await page.goto("/");
    await expect(
      page.getByRole("heading", { name: "Console overview" }),
    ).toBeVisible();

    await page.getByRole("button", { name: "Sign out" }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(
      page.getByRole("heading", { name: "Sign in to the console" }),
    ).toBeVisible();
  });
});
