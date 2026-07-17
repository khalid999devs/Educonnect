import { expect, test, type Page } from "@playwright/test";

import {
  API,
  collection,
  course,
  envelope,
  intakeItem,
  intakeSuggestion,
  mockCsrf,
  mockJson,
  user,
} from "./support";

async function mockIntakeReads(page: Page) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/courses?*`, collection([course()]));
  await mockJson(page, `${API}/api/v1/resources?*`, collection([]));
  await mockJson(page, `${API}/api/v1/intake?*`, collection([intakeItem()]));
  await mockJson(
    page,
    `${API}/api/v1/intake/01jintake00000000000000i`,
    envelope(intakeItem()),
  );
  await mockJson(
    page,
    `${API}/api/v1/intake/01jintake00000000000000i/suggestions`,
    { data: [intakeSuggestion()], meta: { request_id: "e2e" } },
  );
}

test("smart intake shows submit, list, and the honest review-first framing", async ({
  page,
}) => {
  await mockIntakeReads(page);

  await page.goto("/intake");

  await expect(
    page.getByRole("heading", { name: "Smart Intake", level: 1 }),
  ).toBeVisible();
  await expect(page.getByRole("heading", { name: "New intake" })).toBeVisible();
  await expect(
    page.getByText(/create nothing until you review and confirm/i),
  ).toBeVisible();
});

test("selecting an item shows extraction evidence and editable suggestions", async ({
  page,
}) => {
  await mockIntakeReads(page);

  await page.goto("/intake");
  await page
    .getByRole("button", { name: /example.edu\/syllabus/ })
    .first()
    .click();

  /* Extraction evidence precedes any suggestion. */
  await expect(page.getByText(/Extracted 4,200 characters/)).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Review suggestions" }),
  ).toBeVisible();
  await expect(page.getByText(/High confidence/)).toBeVisible();
  await expect(page.getByText(/syllabus lists a chapter-3 quiz/)).toBeVisible();
});

test("confirming an applied suggestion posts decisions", async ({ page }) => {
  await mockCsrf(page);
  await mockIntakeReads(page);

  let decisions: unknown = null;
  await page.route(
    `${API}/api/v1/intake/01jintake00000000000000i/confirmation`,
    async (route) => {
      decisions = route.request().postDataJSON();
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(envelope(intakeItem({ state: "saved" }))),
      });
    },
  );

  await page.goto("/intake");
  await page
    .getByRole("button", { name: /example.edu\/syllabus/ })
    .first()
    .click();

  await page.getByRole("button", { name: "Create this" }).click();
  await page.getByRole("button", { name: /^Confirm/ }).click();

  await expect
    .poll(() => decisions)
    .toMatchObject({
      decisions: [{ id: "01jsuggest0000000000000s", action: "apply" }],
    });
});
