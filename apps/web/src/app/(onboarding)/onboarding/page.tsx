"use client";

import { RequireSession } from "@/components/auth/require-session";
import { OnboardingWizard } from "@/components/onboarding/wizard";

export default function OnboardingPage() {
  return (
    <RequireSession requireVerified>
      <OnboardingWizard />
    </RequireSession>
  );
}
