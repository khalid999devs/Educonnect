import { expect, test, type Page, type Route } from "@playwright/test";

import {
  API,
  collection,
  envelope,
  mentorProfile,
  mentorRequest,
  mockCsrf,
  mockJson,
  user,
} from "./support";

const CORS = {
  "Access-Control-Allow-Origin": "http://localhost:3100",
  "Access-Control-Allow-Credentials": "true",
};

async function routeJson(
  page: Page,
  pattern: RegExp,
  body: unknown,
  status = 200,
) {
  await page.route(pattern, async (route: Route) => {
    await route.fulfill({
      status,
      contentType: "application/json",
      headers: CORS,
      body: JSON.stringify(body),
    });
  });
}

test("the mentor directory lists a verified mentor", async ({ page }) => {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/mentor-profile`,
    { error: { code: "RESOURCE_NOT_FOUND", message: "No profile." } },
    404,
  );
  await mockJson(page, `${API}/api/v1/mentor-requests?*`, collection([]));
  await routeJson(
    page,
    /\/api\/v1\/mentors(\?.*)?$/,
    collection([mentorProfile()]),
  );

  await page.goto("/mentors");

  await expect(
    page.getByRole("heading", { name: "Mentors", level: 1 }),
  ).toBeVisible();
  await expect(page.getByText("Dr Sarah Lee").first()).toBeVisible();
  await expect(page.getByText("Verified").first()).toBeVisible();
});

test("a student can request help from a mentor", async ({ page }) => {
  await mockCsrf(page);
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/mentors/01jmentor00000000000000aaa`,
    envelope(mentorProfile()),
  );
  await mockJson(page, `${API}/api/v1/courses`, collection([]));

  let requestBody: unknown = null;
  await page.route(
    `${API}/api/v1/mentors/01jmentor00000000000000aaa/requests`,
    async (route) => {
      requestBody = route.request().postDataJSON();
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: CORS,
        body: JSON.stringify(envelope(mentorRequest())),
      });
    },
  );

  await page.goto("/mentors/01jmentor00000000000000aaa");

  await expect(
    page.getByRole("heading", { name: "Dr Sarah Lee" }),
  ).toBeVisible();
  await page.getByRole("button", { name: /request help/i }).click();

  await page
    .getByPlaceholder(/Feedback on my/)
    .fill("Dynamic programming review");
  await page
    .getByPlaceholder(/Describe what you're working on/)
    .fill("Could you look over my memoisation approach?");
  await page.getByRole("button", { name: /send request/i }).click();

  await expect(page.getByText(/your request has been sent/i)).toBeVisible();
  expect(requestBody).toMatchObject({ subject: "Dynamic programming review" });
});
