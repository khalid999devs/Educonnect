import { expect, test } from "@playwright/test";

import { API, envelope, mockCsrf, mockJson, onboarding, user } from "./support";

test("guest visiting the app shell is redirected to sign in", async ({
  page,
}) => {
  await mockJson(
    page,
    `${API}/api/v1/me`,
    { error: { code: "UNAUTHENTICATED", message: "Unauthenticated." } },
    401,
  );

  await page.goto("/dashboard");

  await expect(page).toHaveURL(/\/login\?next=%2Fdashboard/);
  await expect(page.getByRole("heading", { name: "Sign in" })).toBeVisible();
});

test("login surfaces field errors from the API envelope", async ({ page }) => {
  await mockCsrf(page);
  await mockJson(
    page,
    `${API}/api/v1/me`,
    { error: { code: "UNAUTHENTICATED", message: "Unauthenticated." } },
    401,
  );
  await mockJson(
    page,
    `${API}/api/v1/auth/login`,
    {
      error: {
        code: "VALIDATION_FAILED",
        message: "The given data was invalid.",
        details: { email: ["These credentials do not match our records."] },
      },
    },
    422,
  );

  await page.goto("/login");
  await page.getByLabel("Email").fill("sam@example.com");
  await page.getByLabel("Password").fill("wrong-password");
  await page.getByRole("button", { name: "Sign in" }).click();

  await expect(
    page.getByText("These credentials do not match our records."),
  ).toBeVisible();
});

test("verified user without onboarding lands in setup after login", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockJson(
    page,
    `${API}/api/v1/me`,
    { error: { code: "UNAUTHENTICATED", message: "Unauthenticated." } },
    401,
  );
  await mockJson(page, `${API}/api/v1/auth/login`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/onboarding`,
    envelope({ onboarding: onboarding() }),
  );

  await page.goto("/login");
  await page.getByLabel("Email").fill("sam@example.com");
  await page.getByLabel("Password").fill("correct-password");
  await page.getByRole("button", { name: "Sign in" }).click();

  await expect(page).toHaveURL(/\/onboarding/);
  await expect(
    page.getByRole("heading", { name: /student workspace/i }),
  ).toBeVisible();
});

test("unverified registration lands on the verify notice", async ({ page }) => {
  await mockCsrf(page);
  await mockJson(
    page,
    `${API}/api/v1/me`,
    { error: { code: "UNAUTHENTICATED", message: "Unauthenticated." } },
    401,
  );
  await mockJson(
    page,
    `${API}/api/v1/auth/register`,
    envelope({ user: user({ email_verified: false }) }),
    201,
  );

  await page.goto("/register");
  await page.getByLabel("Full name").fill("Sam Student");
  await page.getByLabel("Email").fill("sam@example.com");
  await page
    .getByRole("textbox", { name: "Password", exact: true })
    .fill("super-secret-1");
  await page
    .getByRole("textbox", { name: "Confirm password" })
    .fill("super-secret-1");
  await page.getByRole("button", { name: "Create account" }).click();

  await expect(page).toHaveURL(/\/verify-email/);
  await expect(page.getByText("sam@example.com")).toBeVisible();
});
