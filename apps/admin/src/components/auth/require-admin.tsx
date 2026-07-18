"use client";

import { Spinner } from "@educonnect/ui";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

import { useSession } from "@/providers/session-provider";

/**
 * Client route guard for the authenticated console. Server-side authorization
 * on every admin endpoint remains authoritative — this only shapes navigation
 * so a guest never sees the shell. A signed-out or demoted operator is sent to
 * the sign-in page with a `next` hint back to where they were.
 */
export function RequireAdmin({ children }: { children: ReactNode }) {
  const { status } = useSession();
  const router = useRouter();
  const pathname = usePathname();

  const needsLogin = status === "guest";

  useEffect(() => {
    if (needsLogin) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [needsLogin, router, pathname]);

  if (status === "loading" || needsLogin) {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-bg-canvas">
        <Spinner size="lg" label="Verifying your console access" />
      </div>
    );
  }

  return children;
}
