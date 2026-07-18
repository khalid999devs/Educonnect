import { expect, test } from "@playwright/test";

import {
  adminUserRow,
  API,
  auditRow,
  collection,
  envelope,
  FULL_ADMIN_CAPS,
  MODERATOR_CAPS,
  mockCsrf,
  mockJson,
  reportRow,
  sessionWith,
} from "./support";

const ME = `${API}/api/v1/admin/me`;
const USER_ID = "01JUSER00000000000000000AA";
const REPORT_ID = "01JREPORT000000000000000AA";

test.describe("admin operations", () => {
  test("a full admin suspends a user with a recorded reason", async ({
    page,
  }) => {
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
    await mockJson(
      page,
      `${API}/api/v1/admin/users/${USER_ID}/suspension`,
      envelope(
        adminUserRow({
          status: "suspended",
          suspended_at: "2026-07-18T10:00:00Z",
        }),
      ),
    );

    await page.goto("/users");
    await page.getByRole("link", { name: "Priya Patel" }).click();

    await expect(
      page.getByRole("heading", { name: "Priya Patel" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Suspend account" }).click();

    await expect(
      page.getByText(/immediately ends their sessions/),
    ).toBeVisible();
    await page.getByRole("textbox").fill("Repeated policy violations.");
    await page.getByRole("button", { name: "Suspend", exact: true }).click();

    // The dialog closes on success.
    await expect(page.getByText(/immediately ends their sessions/)).toHaveCount(
      0,
    );
  });

  test("a moderator resolves a report from the queue", async ({ page }) => {
    await mockJson(
      page,
      ME,
      envelope(sessionWith(["moderator"], MODERATOR_CAPS)),
    );
    await mockCsrf(page);
    await mockJson(
      page,
      /\/api\/v1\/admin\/reports(\?.*)?$/,
      collection([reportRow()]),
    );
    await mockJson(
      page,
      `${API}/api/v1/admin/reports/${REPORT_ID}/resolution`,
      envelope(reportRow({ status: "actioned" })),
    );

    await page.goto("/reports");
    await expect(page.getByText("Looking for study buddies.")).toBeVisible();

    await page.getByRole("button", { name: "Resolve" }).click();
    await expect(
      page.getByRole("heading", { name: "Resolve report" }),
    ).toBeVisible();
    await page.getByRole("textbox").last().fill("Confirmed spam after review.");
    await page
      .getByRole("button", { name: "Resolve", exact: true })
      .last()
      .click();

    await expect(
      page.getByRole("heading", { name: "Resolve report" }),
    ).toHaveCount(0);
  });

  test("a module the admin lacks the capability for is denied", async ({
    page,
  }) => {
    // A moderator has no roles-view capability, so user management is denied.
    await mockJson(
      page,
      ME,
      envelope(sessionWith(["moderator"], MODERATOR_CAPS)),
    );

    await page.goto("/users");

    await expect(
      page.getByText("You don't have access to this module"),
    ).toBeVisible();
    await expect(page.getByRole("heading", { name: "Users" })).toHaveCount(0);
  });

  test("the audit log shows recorded actions", async ({ page }) => {
    await mockJson(page, ME, envelope(sessionWith(["admin"], FULL_ADMIN_CAPS)));
    await mockJson(
      page,
      /\/api\/v1\/admin\/audit-events(\?.*)?$/,
      collection([auditRow()]),
    );

    await page.goto("/audit");

    await expect(
      page.getByRole("heading", { name: "Audit log" }),
    ).toBeVisible();
    await expect(
      page.getByRole("table").getByText("Users Account Suspended"),
    ).toBeVisible();
    await expect(page.getByText("Repeated policy violations.")).toBeVisible();
  });
});
