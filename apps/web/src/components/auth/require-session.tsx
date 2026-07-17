"use client";

import { Spinner } from "@educonnect/ui";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

import { useSession } from "@/providers/session-provider";

/**
 * Client route guard for authenticated areas. Server-side authorization
 * always remains authoritative — this only shapes navigation.
 */
export function RequireSession({
  children,
  requireVerified = false,
}: {
  children: ReactNode;
  requireVerified?: boolean;
}) {
  const { status, user } = useSession();
  const router = useRouter();
  const pathname = usePathname();

  const needsLogin = status === "guest";
  const needsVerification =
    status === "authenticated" &&
    requireVerified &&
    user?.email_verified === false;

  useEffect(() => {
    if (needsLogin) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    } else if (needsVerification) {
      router.replace("/verify-email");
    }
  }, [needsLogin, needsVerification, router, pathname]);

  if (status === "loading" || needsLogin || needsVerification) {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-bg-canvas">
        <Spinner size="lg" label="Checking your session" />
      </div>
    );
  }

  return children;
}
