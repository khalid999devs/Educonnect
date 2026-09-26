"use client";

import { cn } from "@educonnect/ui";
import { Check } from "lucide-react";
import { Fragment } from "react";

import type { Onboarding, OnboardingStepKey } from "@/lib/api/schemas";
import { ONBOARDING_STEPS } from "@/lib/api/schemas";

const STEP_TITLES: Record<OnboardingStepKey, string> = {
  institution: "Institution",
  program: "Program",
  study_stage: "Study stage",
  courses: "Courses",
  goals: "Goals",
  first_source: "First source",
};

/** Reference-style 6-dot progress rail with connectors and "N of 6". */
export function OnboardingStepper({
  onboarding,
  activeStep,
  onNavigate,
}: {
  onboarding: Onboarding;
  activeStep: OnboardingStepKey | null;
  onNavigate: (step: OnboardingStepKey) => void;
}) {
  const activeIndex =
    activeStep === null
      ? ONBOARDING_STEPS.length
      : ONBOARDING_STEPS.indexOf(activeStep);

  return (
    <nav
      aria-label="Onboarding progress"
      className="flex flex-col items-center"
    >
      <ol className="flex items-center">
        {ONBOARDING_STEPS.map((step, index) => {
          const state = onboarding.steps[step];
          const isActive = step === activeStep;
          const isDone = state === "completed" || state === "skipped";

          return (
            <Fragment key={step}>
              {index > 0 ? (
                <span
                  aria-hidden="true"
                  className={cn(
                    "h-0.5 w-6 sm:w-10",
                    index <= activeIndex
                      ? "bg-brand-primary"
                      : "bg-border-default",
                  )}
                />
              ) : null}
              <li>
                <button
                  type="button"
                  aria-label={`Step ${index + 1}: ${STEP_TITLES[step]}${
                    state === "completed"
                      ? " (completed)"
                      : state === "skipped"
                        ? " (skipped)"
                        : ""
                  }`}
                  aria-current={isActive ? "step" : undefined}
                  onClick={() => onNavigate(step)}
                  className={cn(
                    "flex size-9 items-center justify-center rounded-full border text-body font-semibold tabular-nums transition-colors",
                    "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                    isActive
                      ? "border-brand-focus bg-brand-primary text-white shadow-glow-sm"
                      : isDone
                        ? "border-status-success/50 bg-status-success/15 text-status-success"
                        : "border-border-strong text-text-muted hover:text-text-primary",
                  )}
                >
                  {isDone && !isActive ? (
                    <Check aria-hidden="true" className="size-4" />
                  ) : (
                    index + 1
                  )}
                </button>
              </li>
            </Fragment>
          );
        })}
      </ol>
      <p className="mt-2 text-caption tabular-nums text-text-muted">
        {activeIndex >= ONBOARDING_STEPS.length
          ? "Review"
          : `${activeIndex + 1} of ${ONBOARDING_STEPS.length}`}
      </p>
    </nav>
  );
}
