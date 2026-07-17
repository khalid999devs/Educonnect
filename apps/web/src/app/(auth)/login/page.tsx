"use client";

import { Alert, Button, FormField, Input } from "@educonnect/ui";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { login } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/http";
import { getOnboarding } from "@/lib/api/onboarding";
import { useSession } from "@/providers/session-provider";

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setUser } = useSession();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const user = await login({ email, password });
      setUser(user);

      if (!user.email_verified) {
        router.replace("/verify-email");

        return;
      }

      const onboarding = await getOnboarding();

      if (onboarding.status !== "completed") {
        router.replace("/onboarding");

        return;
      }

      const next = searchParams.get("next");
      router.replace(next?.startsWith("/") ? next : "/dashboard");
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
    <form onSubmit={onSubmit} className="space-y-5" noValidate>
      {error && Object.keys(error.details).length === 0 ? (
        <Alert variant="error" title="Sign in failed">
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
        label="Password"
        required
        error={error?.fieldError("password")}
      >
        {(control) => (
          <Input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            {...control}
          />
        )}
      </FormField>

      <div className="flex items-center justify-between">
        <Link
          href="/forgot-password"
          className="text-body text-brand-primary hover:underline"
        >
          Forgot password?
        </Link>
      </div>

      <Button type="submit" fullWidth glow isLoading={submitting}>
        Sign in
      </Button>
    </form>
  );
}

export default function LoginPage() {
  return (
    <AuthShell
      headline="Welcome back to your"
      highlight="student workspace"
      subtitle="Pick up exactly where you left off — your plan, your materials, your progress."
      title="Sign in"
      footer={
        <>
          New to EduConnect?{" "}
          <Link
            href="/register"
            className="font-medium text-brand-primary hover:underline"
          >
            Create an account
          </Link>
        </>
      }
    >
      <Suspense fallback={null}>
        <LoginForm />
      </Suspense>
    </AuthShell>
  );
}
