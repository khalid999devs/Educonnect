import { expect, test, type Page } from "@playwright/test";

import {
  API,
  collection,
  envelope,
  guidanceBundle,
  mockCsrf,
  mockJson,
  prompt,
  tool,
  user,
  workflow,
} from "./support";

async function mockGuidanceReads(page: Page) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  /* listGuidanceCategories fans out to the three published listings. */
  await mockJson(page, `${API}/api/v1/tools?*`, collection([tool()]));
  await mockJson(page, `${API}/api/v1/prompts?*`, collection([prompt()]));
  await mockJson(page, `${API}/api/v1/workflows?*`, collection([workflow()]));
  await mockJson(page, `${API}/api/v1/guidance?*`, envelope(guidanceBundle()));
}

test("tools & prompts shows a goal chooser and honest guidance framing", async ({
  page,
}) => {
  await mockGuidanceReads(page);

  await page.goto("/tools-prompts");

  await expect(
    page.getByRole("heading", { name: "Tools & prompts", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Choose a goal" }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Study planning" }),
  ).toBeVisible();
  await expect(
    page.getByText(/curated and reviewed by our team/i),
  ).toBeVisible();
});

test("picking a goal renders the tool, prompt, and workflow result blocks", async ({
  page,
}) => {
  await mockGuidanceReads(page);

  await page.goto("/tools-prompts");
  await page.getByRole("button", { name: "Study planning" }).click();

  await expect(
    page.getByRole("heading", { name: "Tools", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Concept Mapper" }),
  ).toBeVisible();
  /* Rationale, cost, privacy, and limitations are all present. */
  await expect(page.getByText(/Why it fits:/)).toBeVisible();
  await expect(page.getByText(/Cost:/)).toBeVisible();
  await expect(page.getByText(/Privacy:/)).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Explain like a study partner" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "From reading to revision notes" }),
  ).toBeVisible();
});

test("saving a tool calls the API and refreshes the bundle", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockGuidanceReads(page);

  let saved = false;
  await page.route(
    `${API}/api/v1/tools/01jtool000000000000000000t/saved`,
    async (route) => {
      if (route.request().method() === "PUT") {
        saved = true;
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          headers: {
            "Access-Control-Allow-Origin": "http://localhost:3100",
            "Access-Control-Allow-Credentials": "true",
          },
          body: JSON.stringify(
            envelope(tool({ viewer_state: { saved: true, dismissed: false } })),
          ),
        });
        return;
      }

      await route.fallback();
    },
  );

  await page.goto("/tools-prompts");
  await page.getByRole("button", { name: "Study planning" }).click();
  await page.getByRole("button", { name: "Save", exact: true }).first().click();

  await expect.poll(() => saved).toBe(true);
});

test("copying a prompt records a copy event", async ({ page }) => {
  await mockCsrf(page);
  await mockGuidanceReads(page);

  let copied = false;
  await page.route(
    `${API}/api/v1/prompts/01jprompt0000000000000000p/copies`,
    async (route) => {
      copied = true;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(
          envelope(
            prompt({
              viewer_state: { saved: false, dismissed: false, copy_count: 1 },
            }),
          ),
        ),
      });
    },
  );

  await page.goto("/tools-prompts");
  await page.getByRole("button", { name: "Study planning" }).click();
  await page.getByRole("button", { name: "Copy prompt" }).click();

  await expect.poll(() => copied).toBe(true);
});
