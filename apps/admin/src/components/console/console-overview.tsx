"use client";

import {
  Alert,
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Skeleton,
} from "@educonnect/ui";
import { ShieldCheck } from "lucide-react";

import { ADMIN_NAV } from "@/components/shell/admin-nav";
import { useSession } from "@/providers/session-provider";

/** Formats a role/capability key such as `admin.access` into a readable label. */
function keyLabel(key: string): string {
  const withoutNamespace = key.includes(".")
    ? key.slice(key.indexOf(".") + 1)
    : key;

  return withoutNamespace
    .split(/[._-]+/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

const PLANNED_MODULES = ADMIN_NAV.flatMap((section) =>
  section.items.filter((item) => !item.available),
);

export function ConsoleOverview() {
  const { session } = useSession();

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Console overview</h1>
        <p className="text-body-lg text-text-secondary">
          {session
            ? `Signed in as ${session.user.name}.`
            : "Loading your session…"}{" "}
          A separate administration surface — its own application, deployment,
          and navigation. No student bundles are shared.
        </p>
      </header>

      <Alert variant="info" title="Foundation only">
        This phase delivers the authenticated console shell. Operational modules
        connect to their administrative APIs in later phases; nothing here shows
        fabricated queues or metrics.
      </Alert>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Your access</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {session ? (
              <>
                <div className="space-y-2">
                  <p className="text-caption font-medium uppercase tracking-wide text-text-muted">
                    Roles
                  </p>
                  <ul className="flex flex-wrap gap-2">
                    {session.authorization.roles.map((role) => (
                      <li key={role}>
                        <Badge variant="brand">{keyLabel(role)}</Badge>
                      </li>
                    ))}
                  </ul>
                </div>
                <div className="space-y-2">
                  <p className="text-caption font-medium uppercase tracking-wide text-text-muted">
                    Capabilities
                  </p>
                  <ul className="flex flex-wrap gap-2">
                    {session.authorization.capabilities.map((capability) => (
                      <li key={capability}>
                        <Badge>{keyLabel(capability)}</Badge>
                      </li>
                    ))}
                  </ul>
                </div>
                <p className="flex items-center gap-1.5 text-caption text-text-muted">
                  <ShieldCheck aria-hidden="true" className="size-3.5" />
                  Every module below unlocks only for the capabilities you hold,
                  and the API re-checks each action.
                </p>
              </>
            ) : (
              <div className="space-y-3">
                <Skeleton className="h-6 w-40" />
                <Skeleton className="h-6 w-56" />
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Planned modules</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-body text-text-secondary">
              These arrive in the operations and hardening phases. They are
              listed here for orientation and remain disabled until their APIs
              ship.
            </p>
            <ul className="flex flex-wrap gap-2">
              {PLANNED_MODULES.map((module) => (
                <li key={module.href}>
                  <Badge>{module.label}</Badge>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
