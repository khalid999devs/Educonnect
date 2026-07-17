"use client";

import {
  Alert,
  Badge,
  Button,
  buttonClasses,
  Card,
  EduConnectThemedLogo,
  Spinner,
  ThemeToggle,
} from "@educonnect/ui";
import {
  BookOpen,
  CalendarDays,
  CircleCheck,
  FolderOpen,
  GraduationCap,
  Search,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState, type ComponentType } from "react";

import { ApiError } from "@/lib/api/http";
import {
  completeOnboarding,
  getOnboarding,
  updateOnboardingStep,
  type StepUpdate,
} from "@/lib/api/onboarding";
import {
  ONBOARDING_STEPS,
  type Onboarding,
  type OnboardingStepKey,
} from "@/lib/api/schemas";
import { OnboardingStepper } from "./stepper";
import {
  CoursesStepForm,
  FirstSourceStepForm,
  GoalsStepForm,
  InstitutionStepForm,
  ProgramStepForm,
  StudyStageStepForm,
  type StepFormProps,
} from "./step-forms";

type WizardView = "welcome" | OnboardingStepKey | "review" | "done";

const STEP_META: Record<
  OnboardingStepKey,
  { headline: string; highlight: string; subtitle: string; optional: boolean }
> = {
  institution: {
    headline: "Your",
    highlight: "institution",
    subtitle: "Where are you studying? This anchors your whole workspace.",
    optional: false,
  },
  program: {
    headline: "Your",
    highlight: "academic setup",
    subtitle: "Help us shape your dashboard — every field is optional.",
    optional: true,
  },
  study_stage: {
    headline: "Where you are",
    highlight: "right now",
    subtitle: "Year and term keep your plan honest about time.",
    optional: true,
  },
  courses: {
    headline: "Your",
    highlight: "courses",
    subtitle: "These become real course workspaces the moment setup finishes.",
    optional: true,
  },
  goals: {
    headline: "What are your",
    highlight: "goals?",
    subtitle: "Guidance and recommendations adapt to what you pick.",
    optional: true,
  },
  first_source: {
    headline: "Add your first",
    highlight: "study source",
    subtitle: "Optional — paste a link and Smart Intake will propose a plan.",
    optional: true,
  },
};

const FORMS: Record<OnboardingStepKey, ComponentType<StepFormProps>> = {
  institution: InstitutionStepForm,
  program: ProgramStepForm,
  study_stage: StudyStageStepForm,
  courses: CoursesStepForm,
  goals: GoalsStepForm,
  first_source: FirstSourceStepForm,
};

function firstPendingStep(onboarding: Onboarding): OnboardingStepKey | null {
  for (const step of ONBOARDING_STEPS) {
    if (onboarding.steps[step] === "pending") {
      return step;
    }
  }

  return null;
}

export function OnboardingWizard() {
  const router = useRouter();
  const [onboarding, setOnboarding] = useState<Onboarding | null>(null);
  const [view, setView] = useState<WizardView | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [stepError, setStepError] = useState<ApiError | null>(null);
  const [conflictNote, setConflictNote] = useState(false);

  useEffect(() => {
    getOnboarding()
      .then((aggregate) => {
        setOnboarding(aggregate);
        setView(
          aggregate.status === "completed"
            ? "done"
            : aggregate.status === "not_started"
              ? "welcome"
              : (aggregate.current_step ??
                firstPendingStep(aggregate) ??
                "review"),
        );
      })
      .catch(() =>
        setLoadError("Could not load your setup. Refresh to retry."),
      );
  }, []);

  const applyUpdate = useCallback(
    (next: Onboarding, currentStep: OnboardingStepKey) => {
      setOnboarding(next);
      setStepError(null);

      const index = ONBOARDING_STEPS.indexOf(currentStep);
      const following = ONBOARDING_STEPS.slice(index + 1).find(
        (step) => next.steps[step] === "pending",
      );

      setView(following ?? firstPendingStep(next) ?? "review");
    },
    [],
  );

  const submitStep = useCallback(
    async (step: OnboardingStepKey, update: StepUpdate) => {
      if (!onboarding || saving) {
        return;
      }

      setSaving(true);
      setStepError(null);
      setConflictNote(false);

      try {
        applyUpdate(
          await updateOnboardingStep(step, onboarding.version, update),
          step,
        );
      } catch (caught) {
        if (caught instanceof ApiError && caught.status === 409) {
          const fresh = await getOnboarding().catch(() => null);

          if (fresh) {
            setOnboarding(fresh);
          }

          setConflictNote(true);
        } else {
          setStepError(
            caught instanceof ApiError
              ? caught
              : new ApiError(0, "NETWORK", "Could not reach the server."),
          );
        }
      } finally {
        setSaving(false);
      }
    },
    [onboarding, saving, applyUpdate],
  );

  const finish = useCallback(async () => {
    if (!onboarding || saving) {
      return;
    }

    setSaving(true);
    setStepError(null);

    try {
      const completed = await completeOnboarding(onboarding.version);
      setOnboarding(completed);
      setView("done");
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 409) {
        const fresh = await getOnboarding().catch(() => null);

        if (fresh) {
          setOnboarding(fresh);
        }

        setConflictNote(true);
      } else {
        setStepError(
          caught instanceof ApiError
            ? caught
            : new ApiError(0, "NETWORK", "Could not reach the server."),
        );
      }
    } finally {
      setSaving(false);
    }
  }, [onboarding, saving]);

  if (loadError) {
    return (
      <div className="flex min-h-dvh items-center justify-center px-6">
        <Alert variant="error" title="Setup unavailable">
          {loadError}
        </Alert>
      </div>
    );
  }

  if (!onboarding || view === null) {
    return (
      <div className="flex min-h-dvh items-center justify-center">
        <Spinner size="lg" label="Loading your setup" />
      </div>
    );
  }

  const activeStep = ONBOARDING_STEPS.includes(view as OnboardingStepKey)
    ? (view as OnboardingStepKey)
    : null;

  const ActiveStepForm = activeStep ? FORMS[activeStep] : null;

  const goBack = () => {
    if (activeStep === null) {
      return;
    }

    const index = ONBOARDING_STEPS.indexOf(activeStep);
    setView(index === 0 ? "welcome" : ONBOARDING_STEPS[index - 1]!);
  };

  return (
    <div className="flex min-h-dvh flex-col bg-bg-canvas">
      <header className="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-6 py-4">
        <Link
          href="/"
          aria-label="EduConnect home"
          className="inline-flex w-fit items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
        >
          <EduConnectThemedLogo width={140} decorative />
        </Link>
        <OnboardingStepper
          onboarding={onboarding}
          activeStep={activeStep}
          onNavigate={(step) => setView(step)}
        />
        <div className="justify-self-end">
          <ThemeToggle />
        </div>
      </header>

      <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col px-6 pb-10">
        {conflictNote ? (
          <Alert variant="warning" title="Setup changed in another tab">
            We reloaded the latest state — review it and continue.
          </Alert>
        ) : null}

        {view === "welcome" ? (
          <WelcomePanel
            onboarding={onboarding}
            onStart={() => setView(firstPendingStep(onboarding) ?? "review")}
          />
        ) : null}

        {activeStep && ActiveStepForm ? (
          <Card className="mt-4 p-7 lg:p-9">
            <div className="mb-6 space-y-1.5">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-h2 text-text-primary">
                  {STEP_META[activeStep].headline}{" "}
                  <span className="text-brand-primary">
                    {STEP_META[activeStep].highlight}
                  </span>
                </h1>
                {STEP_META[activeStep].optional ? (
                  <Badge variant="neutral">Optional</Badge>
                ) : null}
              </div>
              <p className="text-body-lg text-text-secondary">
                {STEP_META[activeStep].subtitle}
              </p>
            </div>
            <ActiveStepForm
              onboarding={onboarding}
              saving={saving}
              error={stepError}
              onSubmit={(update) => void submitStep(activeStep, update)}
              onSkip={
                STEP_META[activeStep].optional
                  ? () => void submitStep(activeStep, { state: "skipped" })
                  : undefined
              }
              onBack={goBack}
            />
          </Card>
        ) : null}

        {view === "review" ? (
          <ReviewPanel
            onboarding={onboarding}
            saving={saving}
            error={stepError}
            onEdit={(step) => setView(step)}
            onFinish={() => void finish()}
          />
        ) : null}

        {view === "done" ? (
          <DonePanel
            onboarding={onboarding}
            onContinue={() => router.replace("/dashboard")}
          />
        ) : null}
      </main>

      <p className="flex items-center justify-center gap-1.5 px-6 pb-6 text-caption text-text-muted">
        <ShieldCheck aria-hidden="true" className="size-3.5" />
        Private by default — your academic data belongs to you.
      </p>
    </div>
  );
}

const WELCOME_CHIPS = [
  {
    icon: CalendarDays,
    title: "Plan",
    sub: "Stay on track",
    tint: "text-brand-primary",
  },
  {
    icon: BookOpen,
    title: "Learn",
    sub: "Smarter, not harder",
    tint: "text-status-info",
  },
  {
    icon: FolderOpen,
    title: "Organize",
    sub: "All in one place",
    tint: "text-status-success",
  },
  {
    icon: Search,
    title: "Research",
    sub: "Find what matters",
    tint: "text-status-research",
  },
];

function WelcomePanel({
  onboarding,
  onStart,
}: {
  onboarding: Onboarding;
  onStart: () => void;
}) {
  return (
    <Card className="mt-4 overflow-hidden p-0">
      <div className="grid lg:grid-cols-[1.15fr_0.85fr]">
        <div className="space-y-6 p-8 lg:p-10">
          <h1 className="text-display text-text-primary">
            Let's build your{" "}
            <span className="text-brand-primary">student workspace</span>
          </h1>
          <p className="max-w-md text-body-lg text-text-secondary">
            From admission to research — six quick steps shape a workspace that
            fits your student life. Only your institution is required;
            everything else can be skipped and finished later.
          </p>
          <div className="grid max-w-md grid-cols-2 gap-3">
            {WELCOME_CHIPS.map((chip) => (
              <div
                key={chip.title}
                className="flex items-center gap-3 rounded-lg border border-border-default bg-bg-canvas px-4 py-3"
              >
                <chip.icon
                  aria-hidden="true"
                  className={`size-5 ${chip.tint}`}
                />
                <span>
                  <span className="block text-body font-semibold text-text-primary">
                    {chip.title}
                  </span>
                  <span className="block text-caption text-text-muted">
                    {chip.sub}
                  </span>
                </span>
              </div>
            ))}
          </div>
        </div>
        <div className="flex flex-col items-center justify-center gap-5 border-t border-border-subtle bg-bg-interactive/50 p-8 text-center lg:border-l lg:border-t-0">
          <span className="flex size-16 items-center justify-center rounded-full bg-bg-surface shadow-glow motion-safe:animate-float">
            <GraduationCap
              aria-hidden="true"
              className="size-8 text-brand-primary"
            />
          </span>
          <p className="text-h4 text-text-primary">
            About two minutes, at your pace
          </p>
          <Button glow size="lg" onClick={onStart}>
            Get started
          </Button>
          <Link
            href="/dashboard"
            className="text-body text-text-secondary hover:text-text-primary hover:underline"
          >
            Do this later
          </Link>
          <p className="text-caption tabular-nums text-text-muted">
            {onboarding.status === "in_progress"
              ? "You have saved progress — we'll pick up where you left off."
              : "Nothing is created until you confirm each step."}
          </p>
        </div>
      </div>
    </Card>
  );
}

const STEP_LABELS: Record<OnboardingStepKey, string> = {
  institution: "Institution",
  program: "Program",
  study_stage: "Study stage",
  courses: "Courses",
  goals: "Goals & problems",
  first_source: "First source",
};

function ReviewPanel({
  onboarding,
  saving,
  error,
  onEdit,
  onFinish,
}: {
  onboarding: Onboarding;
  saving: boolean;
  error: ApiError | null;
  onEdit: (step: OnboardingStepKey) => void;
  onFinish: () => void;
}) {
  const summaries: Record<OnboardingStepKey, string> = {
    institution: onboarding.profile.institution_name
      ? `${onboarding.profile.institution_name} (${onboarding.profile.institution_country_code})`
      : "—",
    program:
      [
        onboarding.profile.degree,
        onboarding.profile.department,
        onboarding.profile.major,
      ]
        .filter(Boolean)
        .join(" · ") || "—",
    study_stage:
      [onboarding.profile.year_label, onboarding.profile.term_label]
        .filter(Boolean)
        .join(" · ") || "—",
    courses:
      onboarding.course_drafts.length > 0
        ? `${onboarding.course_drafts.length} course${onboarding.course_drafts.length === 1 ? "" : "s"}`
        : "—",
    goals:
      onboarding.goals.length + onboarding.problems.length > 0
        ? `${onboarding.goals.length} goals · ${onboarding.problems.length} problems`
        : "—",
    first_source: onboarding.first_source_draft?.url ?? "—",
  };

  return (
    <Card className="mt-4 p-7 lg:p-9">
      <h1 className="text-h2 text-text-primary">
        Review and <span className="text-brand-primary">finish setup</span>
      </h1>
      <p className="mt-1.5 text-body-lg text-text-secondary">
        Completing setup creates your real workspace from exactly what you
        confirmed below.
      </p>

      {error && Object.keys(error.details).length === 0 ? (
        <Alert variant="error" title="Could not finish setup" className="mt-4">
          {error.message}
        </Alert>
      ) : null}

      <ul className="mt-6 space-y-2.5">
        {ONBOARDING_STEPS.map((step) => {
          const state = onboarding.steps[step];

          return (
            <li
              key={step}
              className="flex items-center gap-3 rounded-md border border-border-subtle px-4 py-3"
            >
              <CircleCheck
                aria-hidden="true"
                className={`size-4 shrink-0 ${
                  state === "completed"
                    ? "text-status-success"
                    : "text-border-strong"
                }`}
              />
              <span className="min-w-0 flex-1">
                <span className="block text-body font-medium text-text-primary">
                  {STEP_LABELS[step]}
                </span>
                <span className="block truncate text-caption text-text-muted">
                  {state === "skipped" ? "Skipped" : summaries[step]}
                </span>
              </span>
              <Badge
                variant={
                  state === "completed"
                    ? "success"
                    : state === "skipped"
                      ? "neutral"
                      : "warning"
                }
              >
                {state}
              </Badge>
              <Button variant="ghost" size="sm" onClick={() => onEdit(step)}>
                Edit
              </Button>
            </li>
          );
        })}
      </ul>

      {!onboarding.can_complete ? (
        <Alert variant="info" title="Almost there" className="mt-4">
          To finish, complete your institution and add at least one course,
          goal, or problem — that's what powers a truthful starter workspace.
        </Alert>
      ) : null}

      <div className="mt-6 flex flex-wrap gap-3">
        <Button
          glow
          size="lg"
          isLoading={saving}
          disabled={!onboarding.can_complete}
          onClick={onFinish}
        >
          <Sparkles aria-hidden="true" className="size-4" />
          Finish setup
        </Button>
        <Link href="/dashboard" className={buttonClasses({ variant: "ghost" })}>
          Do this later
        </Link>
      </div>
    </Card>
  );
}

function DonePanel({
  onboarding,
  onContinue,
}: {
  onboarding: Onboarding;
  onContinue: () => void;
}) {
  return (
    <Card className="mt-4 p-8 text-center lg:p-10">
      <span className="mx-auto flex size-16 items-center justify-center rounded-full bg-status-success/15">
        <CircleCheck
          aria-hidden="true"
          className="size-8 text-status-success"
        />
      </span>
      <h1 className="mt-4 text-h2 text-text-primary">
        Your workspace is <span className="text-brand-primary">ready</span>
      </h1>
      <p className="mx-auto mt-2 max-w-md text-body-lg text-text-secondary">
        {onboarding.course_drafts.length > 0
          ? `${onboarding.course_drafts.length} course${onboarding.course_drafts.length === 1 ? "" : "s"} created as real workspaces`
          : "Your workspace was created"}
        {onboarding.goals.length > 0
          ? `, with ${onboarding.goals.length} goal${onboarding.goals.length === 1 ? "" : "s"} guiding your recommendations`
          : ""}
        . Everything on your dashboard comes from these real records.
      </p>
      <div className="mt-6 flex justify-center">
        <Button glow size="lg" onClick={onContinue}>
          Go to your dashboard
        </Button>
      </div>
    </Card>
  );
}
