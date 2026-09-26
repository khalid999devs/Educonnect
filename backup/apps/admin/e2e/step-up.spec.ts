import { expect, test, type Page } from "@playwright/test";

import {
  adminUserRow,
  API,
  collection,
  envelope,
  FULL_ADMIN_CAPS,
  mockCsrf,
  mockJson,
  sessionWith,
} from "./support";

const ME = `${API}/api/v1/admin/me`;
const USER_ID = "01JUSER00000000000000000AA";
const SUSPENSION = `${API}/api/v1/admin/users/${USER_ID}/suspension`;
const REAUTH = `${API}/api/v1/admin/auth/reauth`;

const CORS = {
  "Access-Control-Allow-Origin": "http://localhost:3101",
  "Access-Control-Allow-Credentials": "true",
  "Access-Control-Allow-Headers": "content-type,x-xsrf-token,accept",
  "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
};

/** Suspension replies 423 (re-auth required) until a reauth call has succeeded. */
async function mockGatedSuspension(page: Page): Promise<void> {
  let reauthed = false;

  await page.route(REAUTH, async (route) => {
    reauthed = true;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: CORS,
      body: JSON.stringify(
        envelope({ reauthenticated_until: "2026-07-18T10:05:00Z" }),
      ),
    });
  });

  await page.route(SUSPENSION, async (route) => {
    if (!reauthed) {
      await route.fulfill({
        status: 423,
        contentType: "application/json",
        headers: CORS,
        body: JSON.stringify({
          error: {
            code: "REAUTHENTICATION_REQUIRED",
            message: "Re-authenticate to continue.",
            request_id: "e2e-request",
          },
        }),
      });
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: CORS,
      body: JSON.stringify(
        envelope(
          adminUserRow({
            status: "suspended",
            suspended_at: "2026-07-18T10:00:00Z",
          }),
        ),
      ),
    });
  });
}

async function openSuspendDialog(page: Page): Promise<void> {
  await mockJson(page, ME, envelope(sessionWith(["admin"], FULL_ADMIN_CAPS)));
  await mockCsrf(page);
  await mockJson(
    page,
    /\/api\/v1\/admin\/users(\?.*)?$/,
    collection([adminUserRow()]),
  );
  await mockJson(
    page,
    `${API}/api/v1/admin/users/${USER_ID}`,
    envelope(adminUserRow()),
  );

  await page.goto("/users");
  await page.getByRole("link", { name: "Priya Patel" }).click();
  await expect(
    page.getByRole("heading", { name: "Priya Patel" }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Suspend account" }).click();
  await page.getByRole("textbox").fill("Repeated policy violations.");
  await page.getByRole("button", { name: "Suspend", exact: true }).click();
}

test.describe("admin step-up re-authentication", () => {
  test("a high-risk action prompts for a password and retries after confirmation", async ({
    page,
  }) => {
    await mockGatedSuspension(page);
    await openSuspendDialog(page);

    // The 423 opens the step-up prompt instead of silently failing.
    await expect(
      page.getByRole("heading", { name: "Confirm it's you" }),
    ).toBeVisible();

    await page.getByLabel("Password").fill("secret123");
    await page.getByRole("button", { name: "Confirm" }).click();

    // Both dialogs close once the retried suspension succeeds.
    await expect(
      page.getByRole("heading", { name: "Confirm it's you" }),
    ).toHaveCount(0);
    await expect(page.getByText(/immediately ends their sessions/)).toHaveCount(
      0,
    );
  });

  test("cancelling the prompt surfaces the re-authentication requirement", async ({
    page,
  }) => {
    await mockGatedSuspension(page);
    await openSuspendDialog(page);

    await expect(
      page.getByRole("heading", { name: "Confirm it's you" }),
    ).toBeVisible();

    // Cancel the step-up prompt (the second Cancel; the reason dialog has one too).
    await page.getByRole("button", { name: "Cancel" }).last().click();

    // The prompt closes; the reason dialog stays with the original requirement.
    await expect(
      page.getByRole("heading", { name: "Confirm it's you" }),
    ).toHaveCount(0);
    await expect(
      page.getByText(/immediately ends their sessions/),
    ).toBeVisible();
    await expect(page.getByText("Re-authenticate to continue.")).toBeVisible();
  });
});
