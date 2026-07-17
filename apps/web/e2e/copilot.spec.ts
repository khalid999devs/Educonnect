import { expect, test } from "@playwright/test";

import { API, dashboard, envelope, mockCsrf, mockJson, user } from "./support";

test("copilot chats over the workspace when enabled", async ({ page }) => {
  await mockCsrf(page);
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/dashboard?timezone=*`,
    envelope(dashboard()),
  );
  await mockJson(
    page,
    `${API}/api/v1/copilot/availability`,
    envelope({ copilot: { enabled: true, model: "test-model" } }),
  );
  await mockJson(
    page,
    `${API}/api/v1/copilot/messages`,
    envelope({
      copilot: {
        reply: "From your planner: finish Problem set 2 next.",
        model: "test-model",
        disclaimer: "AI-generated — verify important details.",
      },
    }),
  );

  await page.goto("/dashboard");
  await page.getByRole("button", { name: "Open EduConnect Copilot" }).click();
  await page.getByRole("button", { name: "What should I do next?" }).click();

  await expect(
    page.getByText("From your planner: finish Problem set 2 next."),
  ).toBeVisible();
});

test("copilot shows an honest disabled state without a provider", async ({
  page,
}) => {
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/dashboard?timezone=*`,
    envelope(dashboard()),
  );
  await mockJson(
    page,
    `${API}/api/v1/copilot/availability`,
    envelope({ copilot: { enabled: false, model: "test-model" } }),
  );

  await page.goto("/dashboard");
  await page.getByRole("button", { name: "Open EduConnect Copilot" }).click();

  await expect(page.getByText(/isn't configured on this server/)).toBeVisible();
});
