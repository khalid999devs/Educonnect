"use client";

import { Alert, Button, FormField, Input } from "@educonnect/ui";
import Link from "next/link";
import { useState, type FormEvent } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { forgotPassword } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/http";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await forgotPassword(email);
      setSent(true);
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
    <AuthShell
      headline="Reset your"
      highlight="password"
      subtitle="We'll email you a reset link. For your privacy the response looks the same whether or not the address exists."
      title="Forgot password"
      footer={
        <>
          Remembered it?{" "}
          <Link
            href="/login"
            className="font-medium text-brand-primary hover:underline"
          >
            Back to sign in
          </Link>
        </>
      }
    >
      {sent ? (
        <Alert variant="success" title="Check your inbox">
          If an account exists for {email || "that address"}, a reset link is on
          its way. The link expires shortly, so use it soon.
        </Alert>
      ) : (
        <form onSubmit={onSubmit} className="space-y-5" noValidate>
          {error && Object.keys(error.details).length === 0 ? (
            <Alert variant="error" title="Request failed">
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

          <Button type="submit" fullWidth glow isLoading={submitting}>
            Email me a reset link
          </Button>
        </form>
      )}
    </AuthShell>
  );
}
