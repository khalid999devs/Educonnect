import { expect, test } from "@playwright/test";

import { API, envelope, mockCsrf, mockJson, onboarding, user } from "./support";

test("onboarding resumes, saves the institution, and honors skip", async ({
  page,
}) => {
  await mockCsrf(page);
  await mockJson(page, `${API}/api/v1/me`, envelope({ user: user() }));
  await mockJson(
    page,
    `${API}/api/v1/onboarding`,
    envelope({ onboarding: onboarding() }),
  );

  const afterInstitution = onboarding({
    version: 2,
    status: "in_progress",
    current_step: "program",
    steps: {
      institution: "completed",
      program: "pending",
      study_stage: "pending",
      courses: "pending",
      goals: "pending",
      first_source: "pending",
    },
    profile: {
      institution_name: "University of Dhaka",
      institution_country_code: "BD",
      department: null,
      degree: null,
      major: null,
      year_label: null,
      term_label: null,
    },
  });
  await mockJson(
    page,
    `${API}/api/v1/onboarding/steps/institution`,
    envelope({ onboarding: afterInstitution }),
  );

  await page.goto("/onboarding");
  await expect(
    page.getByRole("heading", { name: /student workspace/i }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Get started" }).click();

  await page
    .getByLabel("University or institution")
    .fill("University of Dhaka");
  await page
    .getByLabel("Institution country")
    .selectOption({ label: "Bangladesh" });

  const afterProgramSkip = onboarding({
    version: 3,
    status: "in_progress",
    current_step: "study_stage",
    steps: {
      institution: "completed",
      program: "skipped",
      study_stage: "pending",
      courses: "pending",
      goals: "pending",
      first_source: "pending",
    },
  });
  await mockJson(
    page,
    `${API}/api/v1/onboarding/steps/program`,
    envelope({ onboarding: afterProgramSkip }),
  );

  await page.getByRole("button", { name: "Continue" }).click();
  await expect(
    page.getByRole("heading", { name: /academic setup/i }),
  ).toBeVisible();

  await page.getByRole("button", { name: "Skip for now" }).click();
  await expect(page.getByRole("heading", { name: /right now/i })).toBeVisible();
});
