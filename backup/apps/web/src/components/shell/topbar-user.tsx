"use client";

import { Button } from "@educonnect/ui";
import { LogOut } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { logout } from "@/lib/api/auth";
import { useSession } from "@/providers/session-provider";

export function TopbarUser() {
  const { user, setUser } = useSession();
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  if (user === null) {
    return null;
  }

  const onSignOut = async () => {
    setBusy(true);

    try {
      await logout();
    } finally {
      setUser(null);
      router.push("/");
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
