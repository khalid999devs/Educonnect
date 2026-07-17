"use client";

import { Alert, Button, buttonClasses, FormField, Input } from "@educonnect/ui";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { resetPassword } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/http";

function ResetPasswordForm() {
  const searchParams = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const initialEmail = searchParams.get("email") ?? "";
  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [confirmError, setConfirmError] = useState<string | undefined>();
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  if (token === "") {
    return (
      <Alert variant="error" title="This link is incomplete">
        Open the reset link from your email again, or request a new one from the
        forgot-password page.
      </Alert>
    );
  }

  if (done) {
    return (
      <div className="space-y-4">
        <Alert variant="success" title="Password updated">
          Your password was changed and other sessions were signed out. Sign in
          with your new password to continue.
        </Alert>
        <Link href="/login" className={buttonClasses({ fullWidth: true })}>
          Go to sign in
        </Link>
      </div>
    );
  }

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();

    if (submitting) {
      return;
    }

    if (password !== confirm) {
      setConfirmError("Passwords do not match.");

      return;
    }

    setConfirmError(undefined);
    setSubmitting(true);
    setError(null);

    try {
      await resetPassword({ token, email, password });
      setDone(true);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught
          : new ApiError(0, "NETWORK", "Could not reach the server."),
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={onSubmit} className="space-y-5" noValidate>
      {error && Object.keys(error.details).length === 0 ? (
        <Alert variant="error" title="Reset failed">
          {error.message}
        </Alert>
      ) : null}

      <FormField label="Email" required error={error?.fieldError("email")}>
        {(control) => (
          <Input
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            {...control}
          />
        )}
      </FormField>

      <FormField
        label="New password"
        required
        hint="At least 8 characters."
        error={error?.fieldError("password")}
      >
        {(control) => (
          <Input
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            {...control}
          />
        )}
      </FormField>

      <FormField label="Confirm new password" required error={confirmError}>
        {(control) => (
          <Input
            type="password"
            autoComplete="new-password"
            value={confirm}
            onChange={(event) => setConfirm(event.target.value)}
            {...control}
          />
        )}
      </FormField>

      <Button type="submit" fullWidth glow isLoading={submitting}>
        Reset password
      </Button>
    </form>
  );
}

export default function ResetPasswordPage() {
  return (
    <AuthShell
      headline="Choose a"
      highlight="new password"
      subtitle="For your security, resetting signs out every other session on your account."
      title="Reset password"
      footer={
        <>
          Need a new link?{" "}
          <Link
            href="/forgot-password"
            className="font-medium text-brand-primary hover:underline"
          >
            Request one
          </Link>
        </>
      }
    >
      <Suspense fallback={null}>
        <ResetPasswordForm />
      </Suspense>
    </AuthShell>
  );
}
