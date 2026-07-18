import { expect, test } from "@playwright/test";

import {
  adminCommunityRow,
  API,
  collection,
  envelope,
  FULL_ADMIN_CAPS,
  mockCsrf,
  mockJson,
  operationalOverview,
  sessionWith,
} from "./support";

const ME = `${API}/api/v1/admin/me`;
const COMMUNITY_ID = "01JCOMM00000000000000000AA";

test.describe("extended admin curation", () => {
  test("an admin archives a community with a recorded reason", async ({
    page,
  }) => {
    await mockJson(page, ME, envelope(sessionWith(["admin"], FULL_ADMIN_CAPS)));
    await mockCsrf(page);
    await mockJson(
      page,
      /\/api\/v1\/admin\/communities(\?.*)?$/,
      collection([adminCommunityRow()]),
    );
    await mockJson(
      page,
      `${API}/api/v1/admin/communities/${COMMUNITY_ID}/visibility`,
      envelope(adminCommunityRow({ visibility: "archived", version: 2 })),
    );

    await page.goto("/communities");
    await expect(page.getByText("Study Skills")).toBeVisible();

    await page.getByRole("button", { name: "Archive" }).click();
    await expect(
      page.getByRole("heading", { name: "Archive community" }),
    ).toBeVisible();
    await page.getByRole("textbox").fill("Low activity.");
    await page
      .getByRole("button", { name: "Archive", exact: true })
      .last()
      .click();

    await expect(
      page.getByRole("heading", { name: "Archive community" }),
    ).toHaveCount(0);
  });

  test("the analytics overview renders aggregate counts", async ({ page }) => {
    await mockJson(page, ME, envelope(sessionWith(["admin"], FULL_ADMIN_CAPS)));
    await mockJson(
      page,
      `${API}/api/v1/admin/analytics`,
      envelope(operationalOverview()),
    );

    await page.goto("/analytics");

    await expect(
      page.getByRole("heading", { name: "Analytics" }),
    ).toBeVisible();
    await expect(page.getByText("Published communities")).toBeVisible();
    await expect(page.getByText("Report backlog")).toBeVisible();
  });

  test("an admin seeds demo content", async ({ page }) => {
    await mockJson(page, ME, envelope(sessionWith(["admin"], FULL_ADMIN_CAPS)));
    await mockCsrf(page);
    await mockJson(
      page,
      `${API}/api/v1/admin/demo-data`,
      envelope({ catalog: { tools: 3, prompts: 2, workflows: 1 } }),
    );

    await page.goto("/demo-data");
    await page.getByRole("textbox").fill("Fresh setup.");
    await page.getByRole("button", { name: "Seed launch content" }).click();

    await expect(page.getByText(/now has 3 tools/)).toBeVisible();
  });
});
