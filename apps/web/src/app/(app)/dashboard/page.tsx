import {
  buttonClasses,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
} from "@educonnect/ui";
import { LayoutDashboard } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = {
  title: "Dashboard",
};

export default function DashboardShellPage() {
  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Dashboard</h1>
        <p className="text-body-lg text-text-secondary">
          This is the Phase 18 application shell. The daily command center is
          wired to the dashboard API in a later phase.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>What exists today</CardTitle>
        </CardHeader>
        <CardContent>
          The shared design system, this student shell (navigation, top bar,
          theme persistence, single Copilot trigger), and the separate admin
          console shell. Nothing on this page is placeholder data — sections
          appear only once their real APIs are connected.
        </CardContent>
      </Card>

      <EmptyState
        icon={LayoutDashboard}
        title="No dashboard modules yet"
        description="Cover, Quick Intake, What's Next, tools, today, Second Brain, progress, and personal rhythm arrive with the dashboard phase — always from your real records."
        action={
          <Link
            href="/design-system"
            className={buttonClasses({ variant: "secondary" })}
          >
            Browse the design system
          </Link>
        }
      />
    </div>
  );
}
