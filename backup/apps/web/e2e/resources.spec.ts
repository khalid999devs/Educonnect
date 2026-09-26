import { expect, test, type Page } from "@playwright/test";

import {
  API,
  collection,
  course,
  envelope,
  mockCsrf,
  mockJson,
  resource,
  user,
} from "./support";

async function mockResourceReads(page: Page) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(page, `${API}/api/v1/courses?*`, collection([course()]));
  /* The list request carries no query params until a filter is applied, so
     match /resources with an optional query string only (not the sub-paths
     like /resources/{id}/download). */
  await page.route(/\/api\/v1\/resources(\?.*)?$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
      body: JSON.stringify(collection([resource()])),
    });
  });
}

test("resources renders the library table and add-material panel", async ({
  page,
}) => {
  await mockResourceReads(page);

  await page.goto("/resources");

  await expect(
    page.getByRole("heading", { name: "Resources", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Add material" }),
  ).toBeVisible();
  await expect(page.getByRole("heading", { name: "Uploads" })).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Your materials" }),
  ).toBeVisible();
  await expect(page.getByText("Operating Systems Notes").first()).toBeVisible();
  /* Ready files expose a download action. */
  await expect(
    page.getByRole("button", { name: 'Download "Operating Systems Notes"' }),
  ).toBeVisible();
});

test("saving a link posts to the links endpoint and refreshes", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockResourceReads(page);

  let linkCreated = false;
  await page.route(`${API}/api/v1/resources/links`, async (route) => {
    linkCreated = true;
    await route.fulfill({
      status: 201,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
      body: JSON.stringify(
        envelope(
          resource({
            id: "01jres0000000000000000000l",
            kind: "link",
            title: "Attention is all you need",
            url: "https://arxiv.org/abs/1706.03762",
            file: null,
          }),
        ),
      ),
    });
  });

  await page.goto("/resources");

  await page.getByRole("button", { name: "Paste link" }).click();
  await page
    .getByRole("textbox", { name: "Title" })
    .fill("Attention is all you need");
  await page
    .getByRole("textbox", { name: "Link" })
    .fill("https://arxiv.org/abs/1706.03762");
  await page.getByRole("button", { name: "Save link" }).click();

  await expect.poll(() => linkCreated).toBe(true);
  await expect(page.getByText("Link saved to your library.")).toBeVisible();
});

test("downloading a ready file requests a short-lived grant", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockResourceReads(page);

  let downloadRequested = false;
  await page.route(
    `${API}/api/v1/resources/01jres0000000000000000000r/download`,
    async (route) => {
      downloadRequested = true;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(
          envelope({
            url: "https://storage.example.com/objects/os-notes?sig=xyz",
            expires_at: "2026-07-16T09:10:00Z",
          }),
        ),
      });
    },
  );

  await page.goto("/resources");

  await page
    .getByRole("button", { name: 'Download "Operating Systems Notes"' })
    .click();

  await expect.poll(() => downloadRequested).toBe(true);
});

test("deleting a resource confirms then calls the API", async ({ page }) => {
  await mockCsrf(page);
  await mockResourceReads(page);

  let deleted = false;
  await page.route(
    `${API}/api/v1/resources/01jres0000000000000000000r`,
    async (route) => {
      if (route.request().method() === "DELETE") {
        deleted = true;
        await route.fulfill({
          status: 202,
          contentType: "application/json",
          headers: {
            "Access-Control-Allow-Origin": "http://localhost:3100",
            "Access-Control-Allow-Credentials": "true",
          },
          body: JSON.stringify(
            envelope(
              resource({
                file: {
                  public_id: "01jfile000000000000000000f",
                  original_name: "os-notes.pdf",
                  declared_mime_type: "application/pdf",
                  verified_mime_type: "application/pdf",
                  expected_size: 204800,
                  verified_size: 204800,
                  status: "deletion_pending",
                  ready_at: "2026-07-16T09:00:00Z",
                },
              }),
            ),
          ),
        });
        return;
      }

      await route.fallback();
    },
  );

  await page.goto("/resources");

  await page
    .getByRole("button", { name: 'Delete "Operating Systems Notes"' })
    .click();

  const dialog = page.getByRole("dialog", { name: "Delete this file?" });
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: "Delete" }).click();

  await expect.poll(() => deleted).toBe(true);
});
