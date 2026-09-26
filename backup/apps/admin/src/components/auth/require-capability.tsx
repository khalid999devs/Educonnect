"use client";

import { EmptyState } from "@educonnect/ui";
import { ShieldAlert } from "lucide-react";
import type { ReactNode } from "react";

import { useSession } from "@/providers/session-provider";

/**
 * Page-level capability guard. A direct navigation to a module the signed-in
 * admin lacks the capability for degrades to an honest denial instead of a
 * failed data load. The API is always authoritative regardless.
 */
export function RequireCapability({
  anyOf,
  children,
}: {
  anyOf: string[];
  children: ReactNode;
}) {
  const { can } = useSession();

  if (!anyOf.some((capability) => can(capability))) {
    return (
      <EmptyState
        icon={ShieldAlert}
        title="You don't have access to this module"
        description="Your role does not include the capability required to view this area. Contact a super administrator if you believe this is a mistake."
      />
    );
  }

  return children;
}
