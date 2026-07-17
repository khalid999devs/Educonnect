import { describe, expect, it } from "vitest";

import { onboardingSchema, userSchema } from "./schemas";

const ONBOARDING_FIXTURE = {
  version: 4,
  status: "in_progress",
  current_step: "courses",
  can_complete: false,
  completed_at: null,
  steps: {
    institution: "completed",
    program: "skipped",
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
  course_drafts: [{ title: "Data Structures", code: "CS-201" }],
  goals: ["Stay organized"],
  problems: [],
  first_source_draft: null,
  starter_context: { anything: true },
};

describe("API schemas", () => {
  it("parses a valid user", () => {
    const user = userSchema.parse({
      id: "01JZX",
      name: "Sam",
      email: "sam@example.com",
      email_verified: false,
      primary_role: "student",
    });

    expect(user.email_verified).toBe(false);
  });

  it("rejects a user without verification state", () => {
    expect(() =>
      userSchema.parse({
        id: "01JZX",
        name: "Sam",
        email: "sam@example.com",
        primary_role: "student",
      }),
    ).toThrow();
  });

  it("parses the onboarding aggregate", () => {
    const onboarding = onboardingSchema.parse(ONBOARDING_FIXTURE);

    expect(onboarding.steps.program).toBe("skipped");
    expect(onboarding.course_drafts[0]?.code).toBe("CS-201");
  });

  it("rejects an onboarding aggregate with an unknown status", () => {
    expect(() =>
      onboardingSchema.parse({ ...ONBOARDING_FIXTURE, status: "finished" }),
    ).toThrow();
  });
});
