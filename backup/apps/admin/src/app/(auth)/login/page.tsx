"use client";

import { Alert, Button, FormField, Input } from "@educonnect/ui";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState, type FormEvent } from "react";

import { AdminAuthShell } from "@/components/auth/admin-auth-shell";
import { adminLogin } from "@/lib/api/admin-auth";
import { ApiError } from "@/lib/api/http";
import { useSession } from "@/providers/session-provider";

function safeNext(next: string | null): string {
  /* Only same-origin absolute paths; never an external or protocol-relative
     redirect target. */
  return next && next.startsWith("/") && !next.startsWith("//") ? next : "/";
}

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { status, setSession } = useSession();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  const destination = safeNext(searchParams.get("next"));

  /* An already-authenticated operator who lands on /login (e.g. via a stale
     bookmark) is forwarded straight into the console. */
  useEffect(() => {
    if (status === "authenticated") {
      router.replace(destination);
    }
  }, [status, destination, router]);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const session = await adminLogin({ email, password });
      setSession(session);
      router.replace(destination);
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

      <Button type="submit" fullWidth isLoading={submitting}>
        Sign in to the console
      </Button>
    </form>
  );
}

export default function AdminLoginPage() {
  return (
    <AdminAuthShell
      title="Sign in to the console"
      description="Enter your administrator credentials. Access is limited to verified accounts with the admin capability."
    >
      <Suspense fallback={null}>
        <LoginForm />
      </Suspense>
    </AdminAuthShell>
  );
}
