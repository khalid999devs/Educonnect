"use client";

import { Alert, Button, FormField, Input } from "@educonnect/ui";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { register } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/http";
import { useSession } from "@/providers/session-provider";

export default function RegisterPage() {
  const router = useRouter();
  const { setUser } = useSession();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [confirmError, setConfirmError] = useState<string | undefined>();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

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
      const user = await register({ name, email, password });
      setUser(user);
      router.replace("/verify-email");
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught
          : new ApiError(0, "NETWORK", "Could not reach the server."),
      );
      setSubmitting(false);
    }
  };

  return (
    <AuthShell
      headline="Let's build your"
      highlight="student workspace"
      subtitle="From admission to research — a workspace that fits your student life."
      title="Create your account"
      footer={
        <>
          Already have an account?{" "}
          <Link
            href="/login"
            className="font-medium text-brand-primary hover:underline"
          >
            Sign in
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit} className="space-y-5" noValidate>
        {error && Object.keys(error.details).length === 0 ? (
          <Alert variant="error" title="Registration failed">
            {error.message}
          </Alert>
        ) : null}

        <FormField label="Full name" required error={error?.fieldError("name")}>
          {(control) => (
            <Input
              autoComplete="name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              {...control}
            />
          )}
        </FormField>

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
          label="Password"
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

        <FormField label="Confirm password" required error={confirmError}>
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
          Create account
        </Button>
      </form>
    </AuthShell>
  );
}
