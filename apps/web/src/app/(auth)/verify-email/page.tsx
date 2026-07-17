"use client";

import { Alert, Button, buttonClasses, Spinner } from "@educonnect/ui";
import { MailCheck } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useRef, useState } from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import {
  resendVerificationEmail,
  verifyEmailWithSignedUrl,
} from "@/lib/api/auth";
import { ApiError } from "@/lib/api/http";
import { useSession } from "@/providers/session-provider";

const API_ORIGIN = new URL(
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000",
).origin;

function VerifyEmailContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const signedUrl = searchParams.get("url");
  const { status, user, setUser } = useSession();
  const [verifying, setVerifying] = useState(false);
  const [verifyError, setVerifyError] = useState<string | null>(null);
  const [resent, setResent] = useState(false);
  const [resendError, setResendError] = useState<string | null>(null);
  const [resending, setResending] = useState(false);
  const attempted = useRef(false);

  /* Only first-party signed URLs are ever fetched. */
  const safeSignedUrl =
    signedUrl !== null && signedUrl.startsWith(`${API_ORIGIN}/`)
      ? signedUrl
      : null;

  useEffect(() => {
    if (
      safeSignedUrl === null ||
      status !== "authenticated" ||
      attempted.current
    ) {
      return;
    }

    attempted.current = true;
    setVerifying(true);

    verifyEmailWithSignedUrl(safeSignedUrl)
      .then((verified) => {
        setUser(verified);
        router.replace("/onboarding");
      })
      .catch((caught: unknown) => {
        setVerifying(false);
        setVerifyError(
          caught instanceof ApiError
            ? caught.status === 403
              ? "This verification link is invalid or expired. Request a new one below."
              : caught.message
            : "Could not reach the server.",
        );
      });
  }, [safeSignedUrl, status, setUser, router]);

  if (status === "loading") {
    return (
      <div className="flex justify-center py-8">
        <Spinner size="lg" label="Checking your session" />
      </div>
    );
  }

  if (status === "guest") {
    const next =
      typeof window !== "undefined"
        ? window.location.pathname + window.location.search
        : "/verify-email";

    return (
      <div className="space-y-4">
        <Alert variant="info" title="Sign in to continue">
          Verification links only work for the signed-in account they were sent
          to. Sign in first and we'll finish this automatically.
        </Alert>
        <Link
          href={`/login?next=${encodeURIComponent(next)}`}
          className={buttonClasses({ fullWidth: true })}
        >
          Sign in
        </Link>
      </div>
    );
  }

  if (user?.email_verified) {
    return (
      <div className="space-y-4">
        <Alert variant="success" title="Email verified">
          {user.email} is verified. You're ready to set up your workspace.
        </Alert>
        <Link href="/onboarding" className={buttonClasses({ fullWidth: true })}>
          Continue to setup
        </Link>
      </div>
    );
  }

  if (verifying) {
    return (
      <div className="flex flex-col items-center gap-3 py-8">
        <Spinner size="lg" label="Verifying your email" />
        <p className="text-body text-text-secondary">Verifying your email…</p>
      </div>
    );
  }

  const onResend = async () => {
    setResending(true);
    setResendError(null);

    try {
      await resendVerificationEmail();
      setResent(true);
    } catch (caught) {
      setResendError(
        caught instanceof ApiError && caught.status === 429
          ? "Please wait a moment before requesting another email."
          : "Could not send the email. Try again shortly.",
      );
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="space-y-5">
      {verifyError ? (
        <Alert variant="error" title="Verification failed">
          {verifyError}
        </Alert>
      ) : null}

      <div className="flex items-start gap-3 rounded-md border border-border-subtle p-4">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-bg-interactive">
          <MailCheck aria-hidden="true" className="size-5 text-brand-primary" />
        </span>
        <p className="text-body text-text-secondary">
          We sent a verification link to{" "}
          <span className="font-medium text-text-primary">{user?.email}</span>.
          Open it on this device to continue — the link expires after a short
          time.
        </p>
      </div>

      {resent ? (
        <Alert variant="success" title="Email sent">
          A fresh verification link is on its way.
        </Alert>
      ) : null}
      {resendError ? (
        <Alert variant="warning" title="Could not resend">
          {resendError}
        </Alert>
      ) : null}

      <Button
        fullWidth
        variant="secondary"
        isLoading={resending}
        onClick={onResend}
      >
        Resend verification email
      </Button>
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <AuthShell
      headline="One quick step to"
      highlight="secure your account"
      subtitle="Verifying your email protects your workspace and unlocks setup."
      title="Verify your email"
      footer={
        <Link
          href="/"
          className="font-medium text-brand-primary hover:underline"
        >
          Back to home
        </Link>
      }
    >
      <Suspense fallback={null}>
        <VerifyEmailContent />
      </Suspense>
    </AuthShell>
  );
}
