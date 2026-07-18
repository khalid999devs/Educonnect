"use client";

import { Badge, Button } from "@educonnect/ui";
import { LogOut } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { adminLogout } from "@/lib/api/admin-auth";
import { useSession } from "@/providers/session-provider";

/** Formats a role key such as `admin` into a readable label. */
function roleLabel(role: string): string {
  return role.charAt(0).toUpperCase() + role.slice(1).replace(/[_-]+/g, " ");
}

export function AdminIdentity() {
  const { session, setSession } = useSession();
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  if (session === null) {
    return null;
  }

  const { user } = session;

  const onSignOut = async () => {
    setBusy(true);

    try {
      await adminLogout();
    } finally {
      /* Clearing the session sends RequireAdmin to the sign-in page even if
         the network logout call could not be reached. */
      setSession(null);
      router.replace("/login");
    }
  };

  return (
    <div className="flex items-center gap-2.5">
      <div className="flex items-center gap-2 rounded-full border border-border-default py-1 pl-1 pr-3">
        <span
          aria-hidden="true"
          className="flex size-7 items-center justify-center rounded-full bg-brand-primary text-caption font-semibold text-white"
        >
          {user.name.charAt(0).toUpperCase()}
        </span>
        <span className="hidden max-w-40 truncate text-caption font-medium text-text-primary md:block">
          {user.name}
        </span>
        <Badge variant="brand">{roleLabel(user.primary_role)}</Badge>
      </div>
      <Button
        variant="ghost"
        size="sm"
        isLoading={busy}
        loadingLabel="Signing out"
        onClick={() => void onSignOut()}
      >
        <LogOut aria-hidden="true" className="size-4" />
        <span className="hidden sm:inline">Sign out</span>
      </Button>
    </div>
  );
}
