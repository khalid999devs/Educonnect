import { expect, test } from "@playwright/test";

import { API, dashboard, envelope, mockCsrf, mockJson, user } from "./support";

test("dashboard renders the approved hierarchy from real API data", async ({
  page,
}) => {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/dashboard?timezone=*`,
    envelope(dashboard()),
  );

  await page.goto("/dashboard");

  await expect(page.getByRole("heading", { name: /Sam/ })).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Quick Intake" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "What's Next" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Recommended study tools" }),
  ).toBeVisible();
  await expect(page.getByRole("heading", { name: "Due today" })).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Second Brain" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "My progress" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Personal rhythm" }),
  ).toBeVisible();
  await expect(
    page.getByText("You completed 0 of 1 tasks due this week."),
  ).toBeVisible();
});

test("completing a task reads the version, updates status, and refreshes", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));

  let refreshed = false;
  await page.route(`${API}/api/v1/dashboard?timezone=*`, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
      body: JSON.stringify(
        envelope(
          refreshed
            ? dashboard({
                whats_next: { tasks: [], overdue_count: 0, upcoming_count: 0 },
                progress: {
                  ...dashboard().progress,
                  completed_task_count: 1,
                  summary: "You completed 1 of 1 tasks due this week.",
                  next_action: null,
                },
              })
            : dashboard(),
        ),
      ),
    });
  });

  await mockJson(
    page,
    `${API}/api/v1/tasks/01jtask000000000000000000t`,
    envelope({ task: { id: "01jtask000000000000000000t", version: 3 } }),
  );

  let statusBody: unknown = null;
  await page.route(
    `${API}/api/v1/tasks/01jtask000000000000000000t/status`,
    async (route) => {
      statusBody = route.request().postDataJSON();
      refreshed = true;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
          "Access-Control-Allow-Headers": "content-type,x-xsrf-token,accept",
          "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
        },
        body: JSON.stringify(
          envelope({ task: { id: "01jtask000000000000000000t" } }),
        ),
      });
    },
  );

  await page.goto("/dashboard");
  await page
    .getByRole("checkbox", { name: 'Mark "Problem set 2" as done' })
    .click();

  await expect(
    page.getByText("You completed 1 of 1 tasks due this week."),
  ).toBeVisible();
  expect(statusBody).toEqual({ expected_version: 3, status: "completed" });
});

test("quick intake rejects non-https links client-side", async ({ page }) => {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/dashboard?timezone=*`,
    envelope(dashboard()),
  );

  await page.goto("/dashboard");
  await page
    .getByLabel("Paste an academic link")
    .fill("http://insecure.example.com");
  await page.getByRole("button", { name: "Capture" }).click();

  await expect(page.getByText("Links must start with https://")).toBeVisible();
});
