import {
  Alert,
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
} from "@educonnect/ui";
import { LayoutDashboard } from "lucide-react";
import type { Metadata } from "next";

import { ADMIN_NAV } from "@/components/shell/admin-nav";

export const metadata: Metadata = {
  title: "Overview",
};

export default function ConsoleOverviewPage() {
  const plannedModules = ADMIN_NAV.flatMap((section) =>
    section.items.filter((item) => !item.available),
  );

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Console overview</h1>
        <p className="text-body-lg text-text-secondary">
          Separate administration surface — its own application, deployment, and
          navigation. No student bundles are shared.
        </p>
      </header>

      <Alert variant="info" title="Shell only">
        Phase 18 delivers this console shell. Operational modules connect to
        real administrative APIs in later phases; nothing here shows fabricated
        queues or metrics.
      </Alert>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Planned modules</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="flex flex-wrap gap-2">
              {plannedModules.map((module) => (
                <li key={module.href}>
                  <Badge>{module.label}</Badge>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
        <EmptyState
          icon={LayoutDashboard}
          title="No operational data yet"
          description="Summaries and queues appear here once the users, content, reports, and audit modules are wired to the API."
        />
      </div>
    </div>
  );
}
