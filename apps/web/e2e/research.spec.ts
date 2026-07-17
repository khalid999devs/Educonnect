import { expect, test, type Page } from "@playwright/test";

import {
  API,
  collection,
  envelope,
  mockCsrf,
  mockJson,
  researchTopic,
  user,
} from "./support";

async function mockResearchReads(page: Page, topics = [researchTopic()]) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/research-topics?*`, collection(topics));
}

test("research shows topics with keywords and source counts", async ({
  page,
}) => {
  await mockResearchReads(page);

  await page.goto("/research");

  await expect(
    page.getByRole("heading", { name: "Research", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Transformer interpretability" }),
  ).toBeVisible();
  await expect(page.getByText("attention").first()).toBeVisible();
  await expect(page.getByText("1 source")).toBeVisible();
});

test("creating a topic posts title and parsed keywords", async ({ page }) => {
  await mockCsrf(page);
  await mockResearchReads(page, []);

  let body: unknown = null;
  await page.route(`${API}/api/v1/research-topics`, async (route) => {
    if (route.request().method() === "POST") {
      body = route.request().postDataJSON();
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(envelope(researchTopic({ title: "New topic" }))),
      });
      return;
    }

    await route.fallback();
  });

  await page.goto("/research");
  await page.getByRole("button", { name: "New topic" }).first().click();

  const dialog = page.getByRole("dialog", { name: "New research topic" });
  await expect(dialog).toBeVisible();
  await dialog.getByRole("textbox", { name: "Title" }).fill("New topic");
  await dialog
    .getByRole("textbox", { name: "Keywords" })
    .fill("attention, probing, attention");
  await dialog.getByRole("button", { name: "Create topic" }).click();

  await expect
    .poll(() => body)
    .toMatchObject({ title: "New topic", keywords: ["attention", "probing"] });
});
