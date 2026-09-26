import { expect, test, type Page } from "@playwright/test";

import {
  API,
  brainCollection,
  brainCollectionEnvelope,
  envelope,
  knowledgeItem,
  knowledgeItemDetail,
  mockCsrf,
  mockJson,
  user,
} from "./support";

async function mockBrainReads(page: Page, items = [knowledgeItem()]) {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/collections?*`,
    brainCollectionEnvelope([brainCollection()]),
  );
  /* Knowledge search carries no params until filtered — match the bare path
     with an optional query, but never the /{id} detail path. */
  await page.route(/\/api\/v1\/knowledge(\?.*)?$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
      body: JSON.stringify(brainCollectionEnvelope(items)),
    });
  });
}

test("second brain renders search, collections, and results", async ({
  page,
}) => {
  await mockBrainReads(page);

  await page.goto("/second-brain");

  await expect(
    page.getByRole("heading", { name: "Second Brain", level: 1 }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Collections" }),
  ).toBeVisible();
  await expect(
    page.getByText("Attention is all you need").first(),
  ).toBeVisible();
});

test("item detail shows the source and supports adding a note", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockBrainReads(page);
  await mockJson(
    page,
    `${API}/api/v1/knowledge/01jknow000000000000000000k`,
    envelope(knowledgeItemDetail()),
  );

  let noteBody: unknown = null;
  await page.route(
    `${API}/api/v1/knowledge/01jknow000000000000000000k/notes`,
    async (route) => {
      noteBody = route.request().postDataJSON();
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        headers: {
          "Access-Control-Allow-Origin": "http://localhost:3100",
          "Access-Control-Allow-Credentials": "true",
        },
        body: JSON.stringify(
          envelope({
            id: "01jnote000000000000000000n",
            version: 1,
            body: "My note",
            created_at: "2026-07-18T09:00:00Z",
            updated_at: "2026-07-18T09:00:00Z",
          }),
        ),
      });
    },
  );

  await page.goto("/second-brain/01jknow000000000000000000k");

  await expect(
    page.getByRole("heading", { name: "Attention is all you need" }),
  ).toBeVisible();
  await expect(page.getByText("arxiv.org/abs/1706.03762")).toBeVisible();

  await page.getByRole("textbox", { name: "Add a note" }).fill("My note");
  await page.getByRole("button", { name: "Add note" }).click();

  await expect.poll(() => noteBody).toEqual({ body: "My note" });
});
