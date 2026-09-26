import { z } from "zod";

/** Runtime schemas for the API responses this app consumes (doc 07). */

export const userSchema = z.object({
  id: z.string().min(1),
  name: z.string(),
  email: z.string(),
  email_verified: z.boolean(),
  primary_role: z.string(),
});

export type User = z.infer<typeof userSchema>;

export const ONBOARDING_STEPS = [
  "institution",
  "program",
  "study_stage",
  "courses",
  "goals",
  "first_source",
] as const;

export const onboardingStepKeySchema = z.enum(ONBOARDING_STEPS);

export type OnboardingStepKey = z.infer<typeof onboardingStepKeySchema>;

const stepStateSchema = z.enum(["pending", "completed", "skipped"]);

export type OnboardingStepState = z.infer<typeof stepStateSchema>;

export const courseDraftSchema = z.object({
  title: z.string(),
  code: z.string().nullable(),
});

export type CourseDraft = z.infer<typeof courseDraftSchema>;

export const onboardingSchema = z.object({
  version: z.number().int().min(0),
  status: z.enum(["not_started", "in_progress", "completed"]),
  current_step: onboardingStepKeySchema.nullable(),
  can_complete: z.boolean(),
  completed_at: z.string().nullable(),
  steps: z.object({
    institution: stepStateSchema,
    program: stepStateSchema,
    study_stage: stepStateSchema,
    courses: stepStateSchema,
    goals: stepStateSchema,
    first_source: stepStateSchema,
  }),
  profile: z.object({
    institution_name: z.string().nullable(),
    institution_country_code: z.string().nullable(),
    department: z.string().nullable(),
    degree: z.string().nullable(),
    major: z.string().nullable(),
    year_label: z.string().nullable(),
    term_label: z.string().nullable(),
  }),
  course_drafts: z.array(courseDraftSchema),
  goals: z.array(z.string()),
  problems: z.array(z.string()),
  first_source_draft: z
    .object({ url: z.string(), title: z.string().nullable() })
    .nullable(),
  starter_context: z.unknown(),
});

export type Onboarding = z.infer<typeof onboardingSchema>;
