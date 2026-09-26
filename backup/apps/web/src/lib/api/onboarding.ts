import { apiFetch, envelopeData } from "./http";
import {
  onboardingSchema,
  type Onboarding,
  type OnboardingStepKey,
} from "./schemas";

function parseOnboarding(payload: unknown): Onboarding {
  const data = envelopeData(payload);

  return onboardingSchema.parse(
    typeof data === "object" && data !== null && "onboarding" in data
      ? (data as { onboarding: unknown }).onboarding
      : data,
  );
}

export async function getOnboarding(): Promise<Onboarding> {
  return parseOnboarding(await apiFetch("/api/v1/onboarding"));
}

export type StepUpdate =
  { state: "skipped" } | { state: "completed"; data: Record<string, unknown> };

export async function updateOnboardingStep(
  step: OnboardingStepKey,
  expectedVersion: number,
  update: StepUpdate,
): Promise<Onboarding> {
  return parseOnboarding(
    await apiFetch(`/api/v1/onboarding/steps/${step}`, {
      method: "PUT",
      body: { expected_version: expectedVersion, ...update },
    }),
  );
}

export async function completeOnboarding(
  expectedVersion: number,
): Promise<Onboarding> {
  return parseOnboarding(
    await apiFetch("/api/v1/onboarding/completion", {
      method: "PUT",
      body: { expected_version: expectedVersion },
    }),
  );
}
