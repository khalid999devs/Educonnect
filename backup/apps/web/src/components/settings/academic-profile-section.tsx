"use client";

import {
  Alert,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  FormField,
  Input,
  Select,
} from "@educonnect/ui";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { GraduationCap } from "lucide-react";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { COUNTRY_OPTIONS } from "@/lib/countries";
import { ApiError } from "@/lib/api/http";
import {
  updateProfile,
  type Settings,
  type SettingsProfile,
} from "@/lib/api/settings";
import { settingsKeys } from "@/lib/query-keys";

/**
 * Academic profile. Writes the existing user_profiles columns through
 * PUT /settings/profile, which is a dedicated action: the onboarding
 * post-completion guard stays exactly as it is.
 *
 * Every field is `present` server-side, so the form always submits all seven,
 * and institution name and country code are validated as a pair.
 */
type FormState = Record<keyof SettingsProfile, string>;

function toFormState(profile: SettingsProfile): FormState {
  return {
    institution_name: profile.institution_name ?? "",
    institution_country_code: profile.institution_country_code ?? "",
    department: profile.department ?? "",
    degree: profile.degree ?? "",
    major: profile.major ?? "",
    year_label: profile.year_label ?? "",
    term_label: profile.term_label ?? "",
  };
}

function toPayload(state: FormState): SettingsProfile {
  const value = (raw: string): string | null => {
    const trimmed = raw.trim();

    return trimmed === "" ? null : trimmed;
  };

  return {
    institution_name: value(state.institution_name),
    institution_country_code: value(state.institution_country_code),
    department: value(state.department),
    degree: value(state.degree),
    major: value(state.major),
    year_label: value(state.year_label),
    term_label: value(state.term_label),
  };
}

export function AcademicProfileSection({
  profile,
}: {
  profile: SettingsProfile;
}) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState<FormState>(() => toFormState(profile));
  const [saved, setSaved] = useState(false);

  const mutation = useMutation({
    mutationFn: (payload: SettingsProfile) => updateProfile(payload),
    onSuccess: (next: Settings) => {
      queryClient.setQueryData(settingsKeys.detail(), next);
      void queryClient.invalidateQueries({ queryKey: settingsKeys.all });
      setForm(toFormState(next.profile));
      setSaved(true);
    },
  });

  const error = mutation.error instanceof ApiError ? mutation.error : null;
  const dirty =
    JSON.stringify(toPayload(form)) !==
    JSON.stringify(toPayload(toFormState(profile)));

  const set = (field: keyof FormState) => (value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
    setSaved(false);
  };

  const institutionPairIncomplete =
    (form.institution_name.trim() === "") !==
    (form.institution_country_code.trim() === "");

  return (
    <Card>
      <CardHeader className="mb-4">
        <div className="flex items-start gap-3">
          <IconChip icon={GraduationCap} accent="settings" size="lg" bordered />
          <div className="space-y-1">
            <CardTitle as="h2">Academic profile</CardTitle>
            <p className="text-body text-text-secondary">
              Where you study and what you study. Leave anything blank that does
              not apply. Nothing here is shared with other students.
            </p>
          </div>
        </div>
      </CardHeader>

      <CardContent>
        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            setSaved(false);
            mutation.mutate(toPayload(form));
          }}
        >
          <div className="grid gap-4 md:grid-cols-2">
            <FormField
              label="Institution"
              error={error?.fieldError("institution_name")}
            >
              {(control) => (
                <Input
                  {...control}
                  value={form.institution_name}
                  maxLength={200}
                  placeholder="University of Dhaka"
                  onChange={(event) =>
                    set("institution_name")(event.target.value)
                  }
                />
              )}
            </FormField>

            <FormField
              label="Country"
              error={error?.fieldError("institution_country_code")}
              hint="Institution and country are saved together."
            >
              {(control) => (
                <Select
                  {...control}
                  value={form.institution_country_code}
                  onChange={(event) =>
                    set("institution_country_code")(event.target.value)
                  }
                >
                  <option value="">Not set</option>
                  {COUNTRY_OPTIONS.map((country) => (
                    <option key={country.code} value={country.code}>
                      {country.name}
                    </option>
                  ))}
                </Select>
              )}
            </FormField>

            <FormField
              label="Department"
              error={error?.fieldError("department")}
            >
              {(control) => (
                <Input
                  {...control}
                  value={form.department}
                  maxLength={160}
                  placeholder="Computer Science and Engineering"
                  onChange={(event) => set("department")(event.target.value)}
                />
              )}
            </FormField>

            <FormField label="Degree" error={error?.fieldError("degree")}>
              {(control) => (
                <Input
                  {...control}
                  value={form.degree}
                  maxLength={160}
                  placeholder="BSc"
                  onChange={(event) => set("degree")(event.target.value)}
                />
              )}
            </FormField>

            <FormField label="Major" error={error?.fieldError("major")}>
              {(control) => (
                <Input
                  {...control}
                  value={form.major}
                  maxLength={160}
                  placeholder="Software Engineering"
                  onChange={(event) => set("major")(event.target.value)}
                />
              )}
            </FormField>

            <FormField
              label="Year"
              error={error?.fieldError("year_label")}
              hint="Free text, for example Year 3 or Final year."
            >
              {(control) => (
                <Input
                  {...control}
                  value={form.year_label}
                  maxLength={80}
                  placeholder="Year 3"
                  onChange={(event) => set("year_label")(event.target.value)}
                />
              )}
            </FormField>

            <FormField
              label="Term"
              error={error?.fieldError("term_label")}
              hint="For example Spring 2026 or Semester 6."
            >
              {(control) => (
                <Input
                  {...control}
                  value={form.term_label}
                  maxLength={80}
                  placeholder="Spring 2026"
                  onChange={(event) => set("term_label")(event.target.value)}
                />
              )}
            </FormField>
          </div>

          {institutionPairIncomplete ? (
            <Alert variant="info" title="Institution needs both parts">
              Add the institution name and its country together, or clear both.
            </Alert>
          ) : null}

          {error ? (
            <Alert variant="error" title="Your profile was not saved">
              {error.message}
            </Alert>
          ) : null}

          {saved && !dirty ? (
            <Alert variant="success" title="Academic profile updated">
              Courses, planner, and resources will use these details.
            </Alert>
          ) : null}

          <div className="flex items-center gap-3">
            <Button
              type="submit"
              disabled={!dirty || institutionPairIncomplete}
              isLoading={mutation.isPending}
              loadingLabel="Saving"
            >
              Save profile
            </Button>
            {dirty ? (
              <Button
                type="button"
                variant="ghost"
                onClick={() => {
                  setForm(toFormState(profile));
                  mutation.reset();
                }}
              >
                Reset
              </Button>
            ) : null}
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
