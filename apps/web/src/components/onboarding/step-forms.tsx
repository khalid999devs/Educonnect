"use client";

import {
  Alert,
  Badge,
  Button,
  cn,
  FormField,
  Input,
  Select,
} from "@educonnect/ui";
import { CircleCheck, Plus, Trash2 } from "lucide-react";
import { useState, type FormEvent } from "react";

import type { StepUpdate } from "@/lib/api/onboarding";
import type { Onboarding } from "@/lib/api/schemas";
import type { ApiError } from "@/lib/api/http";
import { COUNTRY_OPTIONS } from "@/lib/countries";

export type StepFormProps = {
  onboarding: Onboarding;
  saving: boolean;
  error: ApiError | null;
  onSubmit: (update: StepUpdate) => void;
  onSkip?: () => void;
  onBack?: () => void;
};

function StepActions({
  saving,
  onSkip,
  onBack,
  continueLabel = "Continue",
}: {
  saving: boolean;
  onSkip?: () => void;
  onBack?: () => void;
  continueLabel?: string;
}) {
  return (
    <div className="flex flex-wrap items-center gap-3 pt-2">
      {onBack ? (
        <Button type="button" variant="secondary" onClick={onBack}>
          Back
        </Button>
      ) : null}
      <Button type="submit" glow isLoading={saving}>
        {continueLabel}
      </Button>
      {onSkip ? (
        <Button
          type="button"
          variant="ghost"
          disabled={saving}
          onClick={onSkip}
        >
          Skip for now
        </Button>
      ) : null}
    </div>
  );
}

function GeneralError({ error }: { error: ApiError | null }) {
  if (!error || Object.keys(error.details).length > 0) {
    return null;
  }

  return (
    <Alert variant="error" title="Could not save this step">
      {error.message}
    </Alert>
  );
}

export function InstitutionStepForm({
  onboarding,
  saving,
  error,
  onSubmit,
  onBack,
}: StepFormProps) {
  const [name, setName] = useState(onboarding.profile.institution_name ?? "");
  const [country, setCountry] = useState(
    onboarding.profile.institution_country_code ?? "",
  );

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit({
      state: "completed",
      data: { institution_name: name, institution_country_code: country },
    });
  };

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <GeneralError error={error} />
      <FormField
        label="University or institution"
        required
        error={error?.fieldError("institution_name")}
      >
        {(control) => (
          <Input
            placeholder="e.g. University of Dhaka"
            value={name}
            onChange={(event) => setName(event.target.value)}
            {...control}
          />
        )}
      </FormField>
      <FormField
        label="Institution country"
        required
        error={error?.fieldError("institution_country_code")}
      >
        {(control) => (
          <Select
            value={country}
            onChange={(event) => setCountry(event.target.value)}
            {...control}
          >
            <option value="" disabled>
              Select a country
            </option>
            {COUNTRY_OPTIONS.map((option) => (
              <option key={option.code} value={option.code}>
                {option.name}
              </option>
            ))}
          </Select>
        )}
      </FormField>
      <StepActions saving={saving} onBack={onBack} />
    </form>
  );
}

export function ProgramStepForm({
  onboarding,
  saving,
  error,
  onSubmit,
  onSkip,
  onBack,
}: StepFormProps) {
  const [department, setDepartment] = useState(
    onboarding.profile.department ?? "",
  );
  const [degree, setDegree] = useState(onboarding.profile.degree ?? "");
  const [major, setMajor] = useState(onboarding.profile.major ?? "");
  const [emptyError, setEmptyError] = useState<string | null>(null);

  const submit = (event: FormEvent) => {
    event.preventDefault();

    if ([department, degree, major].every((value) => value.trim() === "")) {
      setEmptyError(
        "Fill at least one field to complete this step, or skip it for now.",
      );

      return;
    }

    setEmptyError(null);
    onSubmit({
      state: "completed",
      data: {
        department: department.trim() === "" ? null : department,
        degree: degree.trim() === "" ? null : degree,
        major: major.trim() === "" ? null : major,
      },
    });
  };

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <GeneralError error={error} />
      {emptyError ? (
        <Alert variant="warning" title="Nothing to save yet">
          {emptyError}
        </Alert>
      ) : null}
      <FormField label="Degree" error={error?.fieldError("degree")}>
        {(control) => (
          <Input
            placeholder="e.g. B.Sc."
            value={degree}
            onChange={(event) => setDegree(event.target.value)}
            {...control}
          />
        )}
      </FormField>
      <FormField label="Department" error={error?.fieldError("department")}>
        {(control) => (
          <Input
            placeholder="e.g. Computer Science and Engineering"
            value={department}
            onChange={(event) => setDepartment(event.target.value)}
            {...control}
          />
        )}
      </FormField>
      <FormField label="Program or major" error={error?.fieldError("major")}>
        {(control) => (
          <Input
            placeholder="e.g. Software Engineering"
            value={major}
            onChange={(event) => setMajor(event.target.value)}
            {...control}
          />
        )}
      </FormField>
      <StepActions saving={saving} onSkip={onSkip} onBack={onBack} />
    </form>
  );
}

const YEAR_PRESETS = ["Year 1", "Year 2", "Year 3", "Year 4"];
const TERM_PRESETS = ["Fall 2026", "Spring 2027"];

export function StudyStageStepForm({
  onboarding,
  saving,
  error,
  onSubmit,
  onSkip,
  onBack,
}: StepFormProps) {
  const [yearLabel, setYearLabel] = useState(
    onboarding.profile.year_label ?? "",
  );
  const [termLabel, setTermLabel] = useState(
    onboarding.profile.term_label ?? "",
  );
  const [emptyError, setEmptyError] = useState<string | null>(null);

  const submit = (event: FormEvent) => {
    event.preventDefault();

    if (yearLabel.trim() === "" && termLabel.trim() === "") {
      setEmptyError(
        "Fill at least one field to complete this step, or skip it for now.",
      );

      return;
    }

    setEmptyError(null);
    onSubmit({
      state: "completed",
      data: {
        year_label: yearLabel.trim() === "" ? null : yearLabel,
        term_label: termLabel.trim() === "" ? null : termLabel,
      },
    });
  };

  const presetChip = (
    value: string,
    current: string,
    setter: (value: string) => void,
  ) => (
    <button
      key={value}
      type="button"
      aria-pressed={current === value}
      onClick={() => setter(value)}
      className={cn(
        "rounded-full border px-3 py-1 text-caption font-medium transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
        current === value
          ? "border-brand-primary/50 bg-brand-primary text-white"
          : "border-border-default text-text-secondary hover:text-text-primary",
      )}
    >
      {value}
    </button>
  );

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <GeneralError error={error} />
      {emptyError ? (
        <Alert variant="warning" title="Nothing to save yet">
          {emptyError}
        </Alert>
      ) : null}
      <FormField
        label="Current year"
        hint="Free text. Whatever matches your university."
        error={error?.fieldError("year_label")}
      >
        {(control) => (
          <div className="space-y-2">
            <Input
              placeholder="e.g. Year 2"
              value={yearLabel}
              onChange={(event) => setYearLabel(event.target.value)}
              {...control}
            />
            <div className="flex flex-wrap gap-1.5">
              {YEAR_PRESETS.map((preset) =>
                presetChip(preset, yearLabel, setYearLabel),
              )}
            </div>
          </div>
        )}
      </FormField>
      <FormField label="Current term" error={error?.fieldError("term_label")}>
        {(control) => (
          <div className="space-y-2">
            <Input
              placeholder="e.g. Fall 2026"
              value={termLabel}
              onChange={(event) => setTermLabel(event.target.value)}
              {...control}
            />
            <div className="flex flex-wrap gap-1.5">
              {TERM_PRESETS.map((preset) =>
                presetChip(preset, termLabel, setTermLabel),
              )}
            </div>
          </div>
        )}
      </FormField>
      <StepActions saving={saving} onSkip={onSkip} onBack={onBack} />
    </form>
  );
}

export function CoursesStepForm({
  onboarding,
  saving,
  error,
  onSubmit,
  onSkip,
  onBack,
}: StepFormProps) {
  const [rows, setRows] = useState<Array<{ title: string; code: string }>>(
    onboarding.course_drafts.length > 0
      ? onboarding.course_drafts.map((draft) => ({
          title: draft.title,
          code: draft.code ?? "",
        }))
      : [{ title: "", code: "" }],
  );
  const [rowError, setRowError] = useState<string | null>(null);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const filled = rows
      .map((row) => ({ title: row.title.trim(), code: row.code.trim() }))
      .filter((row) => row.title !== "");

    if (filled.length === 0) {
      setRowError("Add at least one course, or skip this step for now.");

      return;
    }

    setRowError(null);
    onSubmit({
      state: "completed",
      data: {
        courses: filled.map((row) => ({
          title: row.title,
          code: row.code === "" ? null : row.code,
        })),
      },
    });
  };

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <GeneralError error={error} />
      {rowError ? (
        <Alert variant="warning" title="No courses yet">
          {rowError}
        </Alert>
      ) : null}
      <div className="space-y-2.5">
        {rows.map((row, index) => (
          <div key={index} className="flex items-start gap-2">
            <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-[1fr_140px]">
              <Input
                aria-label={`Course ${index + 1} title`}
                placeholder="Course title, e.g. Data Structures"
                value={row.title}
                onChange={(event) =>
                  setRows((current) =>
                    current.map((item, itemIndex) =>
                      itemIndex === index
                        ? { ...item, title: event.target.value }
                        : item,
                    ),
                  )
                }
              />
              <Input
                aria-label={`Course ${index + 1} code`}
                placeholder="Code (optional)"
                value={row.code}
                onChange={(event) =>
                  setRows((current) =>
                    current.map((item, itemIndex) =>
                      itemIndex === index
                        ? { ...item, code: event.target.value }
                        : item,
                    ),
                  )
                }
              />
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              aria-label={`Remove course ${index + 1}`}
              disabled={rows.length === 1}
              onClick={() =>
                setRows((current) =>
                  current.filter((_, itemIndex) => itemIndex !== index),
                )
              }
              className="mt-1"
            >
              <Trash2 aria-hidden="true" className="size-4" />
            </Button>
          </div>
        ))}
      </div>
      <Button
        type="button"
        variant="secondary"
        size="sm"
        disabled={rows.length >= 12}
        onClick={() =>
          setRows((current) => [...current, { title: "", code: "" }])
        }
      >
        <Plus aria-hidden="true" className="size-4" />
        Add another course
      </Button>
      <p className="text-caption text-text-muted">
        Up to 12 courses. These become your real course workspaces when setup
        finishes.
      </p>
      <StepActions saving={saving} onSkip={onSkip} onBack={onBack} />
    </form>
  );
}

const GOAL_SUGGESTIONS = [
  { title: "Stay organized", sub: "Manage tasks and stay on track." },
  { title: "Improve grades", sub: "Boost performance and understanding." },
  { title: "Discover AI tools", sub: "Learn and use AI tools responsibly." },
  { title: "Research support", sub: "Find reliable sources and get help." },
  { title: "Higher studies", sub: "Plan for admissions and beyond." },
  { title: "Career planning", sub: "Explore paths and build your future." },
];

const PROBLEM_SUGGESTIONS = [
  "Too many scattered tools",
  "Deadlines sneak up on me",
  "Messy notes",
  "Hard to start studying",
];

function ChipEditor({
  label,
  hint,
  values,
  onChange,
  suggestions,
  max,
  error,
}: {
  label: string;
  hint: string;
  values: string[];
  onChange: (values: string[]) => void;
  suggestions: string[];
  max: number;
  error?: string;
}) {
  const [draft, setDraft] = useState("");

  const add = (value: string) => {
    const trimmed = value.trim();

    if (trimmed === "" || values.includes(trimmed) || values.length >= max) {
      return;
    }

    onChange([...values, trimmed]);
  };

  return (
    <FormField label={label} hint={hint} error={error}>
      {(control) => (
        <div className="space-y-2.5">
          {values.length > 0 ? (
            <ul className="flex flex-wrap gap-1.5">
              {values.map((value) => (
                <li key={value}>
                  <button
                    type="button"
                    onClick={() =>
                      onChange(values.filter((item) => item !== value))
                    }
                    className="inline-flex items-center gap-1.5 rounded-full border border-brand-primary/40 bg-bg-interactive px-3 py-1 text-caption font-medium text-brand-primary hover:border-status-error/50 hover:text-status-error"
                    aria-label={`Remove ${value}`}
                  >
                    {value}
                    <span aria-hidden="true">×</span>
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
          <div className="flex gap-2">
            <Input
              placeholder="Add your own…"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") {
                  event.preventDefault();
                  add(draft);
                  setDraft("");
                }
              }}
              {...control}
            />
            <Button
              type="button"
              variant="secondary"
              disabled={draft.trim() === "" || values.length >= max}
              onClick={() => {
                add(draft);
                setDraft("");
              }}
            >
              Add
            </Button>
          </div>
          <div className="flex flex-wrap gap-1.5">
            {suggestions
              .filter((suggestion) => !values.includes(suggestion))
              .map((suggestion) => (
                <button
                  key={suggestion}
                  type="button"
                  disabled={values.length >= max}
                  onClick={() => add(suggestion)}
                  className="rounded-full border border-border-default px-3 py-1 text-caption text-text-secondary transition-colors hover:border-brand-focus hover:text-text-primary disabled:opacity-50"
                >
                  + {suggestion}
                </button>
              ))}
          </div>
        </div>
      )}
    </FormField>
  );
}

export function GoalsStepForm({
  onboarding,
  saving,
  error,
  onSubmit,
  onSkip,
  onBack,
}: StepFormProps) {
  const [goals, setGoals] = useState<string[]>(onboarding.goals);
  const [problems, setProblems] = useState<string[]>(onboarding.problems);

  const toggleGoal = (title: string) => {
    setGoals((current) =>
      current.includes(title)
        ? current.filter((item) => item !== title)
        : current.length < 8
          ? [...current, title]
          : current,
    );
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit({ state: "completed", data: { goals, problems } });
  };

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <GeneralError error={error} />
      <fieldset>
        <legend className="text-label text-text-secondary">
          What are your goals?{" "}
          <span className="text-text-muted">(pick up to 8)</span>
        </legend>
        <div className="mt-2.5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {GOAL_SUGGESTIONS.map((goal) => {
            const selected = goals.includes(goal.title);

            return (
              <button
                key={goal.title}
                type="button"
                aria-pressed={selected}
                onClick={() => toggleGoal(goal.title)}
                className={cn(
                  "relative rounded-lg border p-4 text-left transition-all duration-200",
                  "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                  selected
                    ? "border-brand-primary/60 bg-bg-interactive shadow-glow-sm"
                    : "border-border-default bg-bg-canvas hover:border-border-strong",
                )}
              >
                <CircleCheck
                  aria-hidden="true"
                  className={cn(
                    "absolute right-3 top-3 size-4",
                    selected ? "text-brand-primary" : "text-border-strong",
                  )}
                />
                <span className="block pr-6 text-body font-semibold text-text-primary">
                  {goal.title}
                </span>
                <span className="mt-1 block text-caption text-text-muted">
                  {goal.sub}
                </span>
              </button>
            );
          })}
        </div>
        {goals.filter((goal) =>
          GOAL_SUGGESTIONS.every((suggestion) => suggestion.title !== goal),
        ).length > 0 ? (
          <ul className="mt-2.5 flex flex-wrap gap-1.5">
            {goals
              .filter((goal) =>
                GOAL_SUGGESTIONS.every(
                  (suggestion) => suggestion.title !== goal,
                ),
              )
              .map((goal) => (
                <li key={goal}>
                  <button
                    type="button"
                    onClick={() => toggleGoal(goal)}
                    className="inline-flex items-center gap-1.5 rounded-full border border-brand-primary/40 bg-bg-interactive px-3 py-1 text-caption font-medium text-brand-primary"
                    aria-label={`Remove ${goal}`}
                  >
                    {goal} <span aria-hidden="true">×</span>
                  </button>
                </li>
              ))}
          </ul>
        ) : null}
        <div className="mt-2.5 max-w-sm">
          <CustomGoalInput
            disabled={goals.length >= 8}
            onAdd={(goal) => toggleGoal(goal)}
          />
        </div>
      </fieldset>

      <ChipEditor
        label="What slows you down today?"
        hint="Optional. Helps tailor guidance to real problems."
        values={problems}
        onChange={setProblems}
        suggestions={PROBLEM_SUGGESTIONS}
        max={8}
        error={error?.fieldError("problems")}
      />

      <p className="flex items-center gap-1.5 text-caption text-text-muted">
        <Badge variant="ai">Adaptive</Badge>
        Your dashboard and recommendations adapt to these, from real records
        only.
      </p>

      <StepActions saving={saving} onSkip={onSkip} onBack={onBack} />
    </form>
  );
}

function CustomGoalInput({
  disabled,
  onAdd,
}: {
  disabled: boolean;
  onAdd: (goal: string) => void;
}) {
  const [draft, setDraft] = useState("");

  return (
    <div className="flex gap-2">
      <Input
        aria-label="Add a custom goal"
        placeholder="Add a custom goal…"
        value={draft}
        disabled={disabled}
        onChange={(event) => setDraft(event.target.value)}
        onKeyDown={(event) => {
          if (event.key === "Enter") {
            event.preventDefault();

            if (draft.trim() !== "") {
              onAdd(draft.trim());
              setDraft("");
            }
          }
        }}
      />
      <Button
        type="button"
        variant="secondary"
        disabled={disabled || draft.trim() === ""}
        onClick={() => {
          onAdd(draft.trim());
          setDraft("");
        }}
      >
        Add
      </Button>
    </div>
  );
}

export function FirstSourceStepForm({
  onboarding,
  saving,
  error,
  onSubmit,
  onSkip,
  onBack,
}: StepFormProps) {
  const [url, setUrl] = useState(onboarding.first_source_draft?.url ?? "");
  const [title, setTitle] = useState(
    onboarding.first_source_draft?.title ?? "",
  );

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit({
      state: "completed",
      data: { url, title: title.trim() === "" ? null : title },
    });
  };

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <GeneralError error={error} />
      <FormField
        label="Link to a first study source"
        hint="A syllabus page, lecture notes, or a reading. Smart Intake processes it after setup, with your review."
        error={error?.fieldError("url")}
      >
        {(control) => (
          <Input
            type="url"
            placeholder="https://…"
            value={url}
            onChange={(event) => setUrl(event.target.value)}
            {...control}
          />
        )}
      </FormField>
      <FormField label="Title (optional)" error={error?.fieldError("title")}>
        {(control) => (
          <Input
            placeholder="e.g. CS-201 syllabus"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            {...control}
          />
        )}
      </FormField>
      <Alert variant="info" title="Nothing is fetched yet">
        The link is stored as a draft. File uploads and extraction run through
        Smart Intake once your workspace exists. Suggestions only; you confirm
        everything.
      </Alert>
      <StepActions
        saving={saving}
        onSkip={onSkip}
        onBack={onBack}
        continueLabel="Save source"
      />
    </form>
  );
}
