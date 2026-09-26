import { expect, test, type Page } from "@playwright/test";

import {
  API,
  collection,
  course,
  envelope,
  mockCsrf,
  mockJson,
  template,
  templateCopy,
  user,
} from "./support";

async function mockTemplateReads(
  page: Page,
  options: { copies?: unknown[] } = {},
) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/courses?*`, collection([course()]));
  /* Library list carries no query params until filtered — match the bare
     path with an optional query, but never the /{id}/copies sub-path. */
  await page.route(/\/api\/v1\/templates(\?.*)?$/, async (route) => {
    if (route.request().method() !== "GET") {
      await route.fallback();
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
      body: JSON.stringify(collection([template()])),
    });
  });
  await mockJson(
    page,
    `${API}/api/v1/template-copies?*`,
    collection(options.copies ?? []),
  );
}

test("templates renders the library grid and the copies rail", async ({
  page,
}) => {
  await mockTemplateReads(page);

  await page.goto("/templates");

  await expect(
    page.getByRole("heading", { name: "Templates", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Template library" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Assignment structure" }),
  ).toBeVisible();
  /* Approved-free badge, never "Premium". */
  await expect(page.getByText("Approved · Free").first()).toBeVisible();
  await expect(page.getByText("Premium")).toHaveCount(0);
  await expect(page.getByRole("heading", { name: "My copies" })).toBeVisible();
});

test("previewing a template shows its latest version body", async ({
  page,
}) => {
  await mockTemplateReads(page);

  await page.goto("/templates");
  await page.getByRole("button", { name: "Preview" }).click();

  const dialog = page.getByRole("dialog", { name: "Assignment structure" });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText("## Introduction")).toBeVisible();
});

test("using a template copies it to a chosen destination", async ({ page }) => {
  await mockCsrf(page);
  await mockTemplateReads(page);

  let copied: unknown = null;
  await page.route(
    `${API}/api/v1/templates/01jtmpl0000000000000000000/copies`,
    async (route) => {
      copied = route.request().postDataJSON();
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(envelope(templateCopy())),
      });
    },
  );

  await page.goto("/templates");
  await page.getByRole("button", { name: "Use template" }).click();

  const dialog = page.getByRole("dialog", { name: "Use this template" });
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: "Create copy" }).click();

  await expect.poll(() => copied).toEqual({ destination: "dashboard" });
  await expect(
    page.getByText("Added to your library — edit your copy any time."),
  ).toBeVisible();
});

test("editing a copy saves with optimistic concurrency", async ({ page }) => {
  await mockCsrf(page);
  await mockTemplateReads(page, { copies: [templateCopy()] });

  let body: unknown = null;
  await page.route(
    `${API}/api/v1/template-copies/01jcopy0000000000000000000`,
    async (route) => {
      if (route.request().method() === "PUT") {
        body = route.request().postDataJSON();
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          headers: {
            "Access-Control-Allow-Origin": "http://localhost:3100",
            "Access-Control-Allow-Credentials": "true",
          },
          body: JSON.stringify(
            envelope(templateCopy({ title: "Edited", version: 2 })),
          ),
        });
        return;
      }

      await route.fallback();
    },
  );

  await page.goto("/templates");
  await page.getByRole("button", { name: "Edit" }).click();

  const dialog = page.getByRole("dialog", { name: "Edit your copy" });
  await expect(dialog).toBeVisible();
  await dialog.getByRole("textbox", { name: "Title" }).fill("Edited");
  await dialog.getByRole("button", { name: "Save changes" }).click();

  await expect
    .poll(() => body)
    .toMatchObject({ expected_version: 1, title: "Edited" });
});
