import { expect, test, type Page } from "@playwright/test";

import {
  API,
  collection,
  course,
  envelope,
  mockCsrf,
  mockJson,
  plannerWindow,
  task,
  user,
} from "./support";

async function mockPlannerReads(page: Page) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/courses?*`, collection([course()]));
  await mockJson(page, `${API}/api/v1/tasks?*`, collection([task()]));
  await mockJson(
    page,
    `${API}/api/v1/planner/weekly?*`,
    plannerWindow("week_start", "2026-07-13"),
  );
  await mockJson(
    page,
    `${API}/api/v1/planner/agenda?*`,
    plannerWindow("date", "2026-07-17"),
  );
}

test("planner renders the weekly schedule, agenda, and rail from API data", async ({
  page,
}) => {
  await mockPlannerReads(page);

  await page.goto("/planner");

  await expect(
    page.getByRole("heading", { name: "Planner", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Weekly schedule" }),
  ).toBeVisible();
  await expect(page.getByRole("heading", { name: /agenda/i })).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Upcoming deadline" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Focus session" }),
  ).toBeVisible();
  /* The seeded task appears in both the agenda and the week. */
  await expect(page.getByText("Problem set 2").first()).toBeVisible();
});

test("creating a task posts to the API and refreshes the planner", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockPlannerReads(page);

  let created = false;
  await page.route(`${API}/api/v1/tasks`, async (route) => {
    if (route.request().method() === "POST") {
      created = true;
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(envelope(task({ title: "New reading" }))),
      });
      return;
    }

    await route.fallback();
  });

  await page.goto("/planner");

  await page.getByRole("button", { name: "Add task" }).click();

  const dialog = page.getByRole("dialog", { name: "New task" });
  await expect(dialog).toBeVisible();

  await dialog.getByRole("textbox", { name: "Title" }).fill("New reading");
  await dialog.getByRole("button", { name: "Create task" }).click();

  await expect.poll(() => created).toBe(true);
  await expect(dialog).toBeHidden();
});

test("completing a task from the agenda updates its status", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/courses?*`, collection([course()]));
  await mockJson(page, `${API}/api/v1/tasks?*`, collection([task()]));
  await mockJson(
    page,
    `${API}/api/v1/planner/weekly?*`,
    plannerWindow("week_start", "2026-07-13", {
      tasks: [],
      focus_sessions: [],
    }),
  );
  await mockJson(
    page,
    `${API}/api/v1/planner/agenda?*`,
    plannerWindow("date", "2026-07-17", { focus_sessions: [] }),
  );

  let statusUpdated = false;
  await page.route(
    `${API}/api/v1/tasks/01jtask000000000000000000t/status`,
    async (route) => {
      statusUpdated = true;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(
          envelope(task({ status: "completed", version: 2 })),
        ),
      });
    },
  );

  await page.goto("/planner");

  const checkbox = page
    .getByRole("checkbox", { name: /Mark "Problem set 2" completed/ })
    .first();
  await checkbox.click();

  await expect.poll(() => statusUpdated).toBe(true);
});
