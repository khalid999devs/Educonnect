import { expect, test, type Page, type Route } from "@playwright/test";

import {
  API,
  collection,
  community,
  contentReport,
  envelope,
  mentorProfile,
  mockCsrf,
  mockJson,
  post,
  user,
} from "./support";

const CORS = {
  "Access-Control-Allow-Origin": "http://localhost:3100",
  "Access-Control-Allow-Credentials": "true",
};

async function routeJson(page: Page, pattern: RegExp, body: unknown) {
  await page.route(pattern, async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: CORS,
      body: JSON.stringify(body),
    });
  });
}

async function mockCommunityReads(
  page: Page,
  posts = [post()],
  communities = [community()],
) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/communities?*`, collection(communities));
  await mockJson(
    page,
    `${API}/api/v1/mentors?*`,
    collection([mentorProfile()]),
  );
  await mockJson(page, `${API}/api/v1/resources?*`, collection([]));
  await routeJson(page, /\/api\/v1\/feed(\?.*)?$/, collection(posts));
}

test("community shows the feed, communities rail, and a post", async ({
  page,
}) => {
  await mockCommunityReads(page);

  await page.goto("/community");

  await expect(
    page.getByRole("heading", { name: "Community", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Communities" }),
  ).toBeVisible();
  await expect(
    page.getByText("Looking for study buddies", { exact: false }),
  ).toBeVisible();
});

test("a member can report someone else's post", async ({ page }) => {
  await mockCsrf(page);
  await mockCommunityReads(page, [post({ is_mine: false })]);

  let reportBody: unknown = null;
  await page.route(
    `${API}/api/v1/posts/01jpost0000000000000000aaa/reports`,
    async (route) => {
      reportBody = route.request().postDataJSON();
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: CORS,
        body: JSON.stringify(envelope(contentReport())),
      });
    },
  );

  await page.goto("/community");

  await page
    .getByRole("button", { name: /report/i })
    .first()
    .click();
  await expect(
    page.getByRole("heading", { name: /report this post/i }),
  ).toBeVisible();
  await page.getByRole("button", { name: /send report/i }).click();

  await expect(page.getByText(/moderators will review/i)).toBeVisible();
  expect(reportBody).toMatchObject({ reason: "spam" });
});

test("a member can post to a community", async ({ page }) => {
  await mockCsrf(page);
  await mockCommunityReads(page);

  let createBody: unknown = null;
  await page.route(
    `${API}/api/v1/communities/01jcommunity0000000000000a/posts`,
    async (route) => {
      createBody = route.request().postDataJSON();
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: CORS,
        body: JSON.stringify(envelope(post({ is_mine: true }))),
      });
    },
  );

  await page.goto("/community");

  await page
    .getByPlaceholder(/Ask a question/)
    .fill("Anyone revising graph theory this weekend?");
  await page.getByRole("button", { name: "Post", exact: true }).click();

  await expect(page.getByText("Your post is live.")).toBeVisible();
  expect(createBody).toMatchObject({
    body: "Anyone revising graph theory this weekend?",
  });
});
