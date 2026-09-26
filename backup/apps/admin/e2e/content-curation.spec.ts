import { expect, test } from "@playwright/test";

import {
  adminToolRow,
  API,
  collection,
  envelope,
  FULL_ADMIN_CAPS,
  mockCsrf,
  mockJson,
  sessionWith,
} from "./support";

const ME = `${API}/api/v1/admin/me`;
const TOOL_ID = "01JTOOL00000000000000000AA";

test.describe("content curation", () => {
  test("an admin advances a draft tool through its lifecycle", async ({
    page,
  }) => {
    await mockJson(page, ME, envelope(sessionWith(["admin"], FULL_ADMIN_CAPS)));
    await mockCsrf(page);
    await mockJson(
      page,
      /\/api\/v1\/admin\/content\/tools(\?.*)?$/,
      collection([adminToolRow()]),
    );
    await mockJson(
      page,
      `${API}/api/v1/admin/content/tools/${TOOL_ID}/lifecycle`,
      envelope(adminToolRow({ state: "in_review", version: 2 })),
    );

    await page.goto("/content/tools");

    await expect(
      page.getByRole("heading", { name: "Content curation" }),
    ).toBeVisible();
    await expect(page.getByText("Concept Mapper")).toBeVisible();

    // A draft offers "Submit for review".
    await page.getByRole("button", { name: "Submit for review" }).click();
    await expect(
      page.getByText("This lifecycle change is recorded"),
    ).toBeVisible();
    await page.getByRole("textbox").fill("Ready for review.");
    await page
      .getByRole("button", { name: "Submit for review", exact: true })
      .last()
      .click();

    // The dialog closes on success.
    await expect(
      page.getByText("This lifecycle change is recorded"),
    ).toHaveCount(0);
  });

  test("a moderator cannot reach content curation", async ({ page }) => {
    await mockJson(
      page,
      ME,
      envelope(
        sessionWith(["moderator"], ["admin.access", "moderation.scoped"]),
      ),
    );

    await page.goto("/content/tools");

    await expect(
      page.getByText("You don't have access to this module"),
    ).toBeVisible();
  });
});
