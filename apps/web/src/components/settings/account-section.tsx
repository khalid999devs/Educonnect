"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  FormField,
  Input,
} from "@educonnect/ui";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { AtSign, ShieldCheck, UserRound } from "lucide-react";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { ApiError } from "@/lib/api/http";
import { updateAccount, type Settings } from "@/lib/api/settings";
import { settingsKeys } from "@/lib/query-keys";
import { useSession } from "@/providers/session-provider";

/**
 * Account details. Name is the only writable field: email changes, password
 * changes while signed in, and delete/export account each need work that is
 * explicitly deferred, so this surface does not imply they exist.
 */
export function AccountSection({ settings }: { settings: Settings }) {
  const queryClient = useQueryClient();
  const { refresh } = useSession();
  const [name, setName] = useState(settings.account.name);
  const [saved, setSaved] = useState(false);

  const mutation = useMutation({
    mutationFn: (value: string) => updateAccount({ name: value }),
    onSuccess: (next) => {
      queryClient.setQueryData(settingsKeys.detail(), next);
      void queryClient.invalidateQueries({ queryKey: settingsKeys.all });
      /* The top bar renders the name from the session, not from this query. */
      void refresh();
      setSaved(true);
    },
  });

  const error = mutation.error instanceof ApiError ? mutation.error : null;
  const trimmed = name.trim();
  const dirty = trimmed !== settings.account.name;
  const memberSince = settings.account.created_at;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="mb-4">
          <div className="flex items-start gap-3">
            <IconChip icon={UserRound} accent="settings" size="lg" bordered />
            <div className="space-y-1">
              <CardTitle as="h2">Account</CardTitle>
              <p className="text-body text-text-secondary">
                How you are named across EduConnect. Your email address is used
                to sign in and cannot be changed here.
              </p>
            </div>
          </div>
        </CardHeader>

        <CardContent>
          <form
            className="max-w-md space-y-4"
            onSubmit={(event) => {
              event.preventDefault();
              setSaved(false);
              mutation.mutate(trimmed);
            }}
          >
            <FormField
              label="Display name"
              required
              error={error?.fieldError("name")}
              hint="Shown in the top bar, on your posts, and to mentors you contact."
            >
              {(control) => (
                <Input
                  {...control}
                  value={name}
                  maxLength={255}
                  autoComplete="name"
                  onChange={(event) => {
                    setName(event.target.value);
                    setSaved(false);
                  }}
                />
              )}
            </FormField>

            {error && error.fieldError("name") === undefined ? (
              <Alert variant="error" title="Your name was not saved">
                {error.message}
              </Alert>
            ) : null}

            {saved && !dirty ? (
              <Alert variant="success" title="Name updated">
                Everywhere your name appears now reads {settings.account.name}.
              </Alert>
            ) : null}

            <div className="flex items-center gap-3">
              <Button
                type="submit"
                disabled={!dirty || trimmed === ""}
                isLoading={mutation.isPending}
                loadingLabel="Saving"
              >
                Save name
              </Button>
              {dirty ? (
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => {
                    setName(settings.account.name);
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

      <Card>
        <CardHeader className="mb-4">
          <CardTitle as="h2">Sign-in identity</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2.5">
          <div className="flex items-center gap-3 rounded-md border border-border-subtle px-3.5 py-3 transition-colors hover:border-border-strong">
            <IconChip icon={AtSign} accent="settings" />
            <div className="min-w-0 flex-1">
              <p className="text-caption text-text-muted">Email address</p>
              <p className="truncate text-body font-medium text-text-primary">
                {settings.account.email}
              </p>
            </div>
            <Badge
              variant={settings.account.email_verified ? "success" : "warning"}
            >
              {settings.account.email_verified ? "Verified" : "Unverified"}
            </Badge>
          </div>

          <div className="flex items-center gap-3 rounded-md border border-border-subtle px-3.5 py-3 transition-colors hover:border-border-strong">
            <IconChip icon={ShieldCheck} accent="settings" />
            <div className="min-w-0 flex-1">
              <p className="text-caption text-text-muted">Member since</p>
              <p className="text-body font-medium tabular-nums text-text-primary">
                {memberSince === null
                  ? "Not recorded"
                  : new Date(memberSince).toLocaleDateString(undefined, {
                      year: "numeric",
                      month: "long",
                      day: "numeric",
                    })}
              </p>
            </div>
            {settings.onboarding_completed ? (
              <Badge variant="neutral">Onboarding complete</Badge>
            ) : null}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
