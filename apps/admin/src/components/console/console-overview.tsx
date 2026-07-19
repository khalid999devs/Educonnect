"use client";

import {
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import {
  Activity,
  FileCheck2,
  Flag,
  ShieldCheck,
  Users,
  Users2,
} from "lucide-react";

import {
  getOperationalOverview,
  type OperationalOverview,
} from "@/lib/api/admin-analytics";
import { analyticsKeys } from "@/lib/query-keys";
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

const CONTENT_TYPES = [
  { key: "tools", label: "Tools" },
  { key: "prompts", label: "Prompts" },
  { key: "workflows", label: "Workflows" },
  { key: "templates", label: "Templates" },
] as const;

const STATE_SEGMENTS = [
  { key: "published", label: "Published", color: "bg-status-success" },
  { key: "in_review", label: "In review", color: "bg-status-warning" },
  { key: "draft", label: "Draft", color: "bg-status-info" },
  { key: "archived", label: "Archived", color: "bg-bg-interactive" },
] as const;

function Stat({
  icon: Icon,
  label,
  value,
  hint,
  emphasis = false,
}: {
  icon: typeof Users;
  label: string;
  value: number;
  hint: string;
  emphasis?: boolean;
}) {
  return (
    <Card>
      <CardContent className="space-y-3 p-5">
        <div className="flex items-center gap-2 text-text-muted">
          <Icon aria-hidden="true" className="size-4" />
          <span className="text-caption font-medium uppercase tracking-wide">
            {label}
          </span>
        </div>
        <p
          className={cn(
            "text-h2 tabular-nums",
            emphasis ? "text-status-deadline" : "text-text-primary",
          )}
        >
          {value.toLocaleString()}
        </p>
        <p className="text-caption text-text-muted">{hint}</p>
      </CardContent>
    </Card>
  );
}

function OverviewMetrics({ data }: { data: OperationalOverview }) {
  const publishedTotal = CONTENT_TYPES.reduce(
    (sum, type) => sum + data.content[type.key].published,
    0,
  );
  const roleEntries = Object.entries(data.users.by_role).sort(
    (a, b) => b[1] - a[1],
  );
  const maxRole = Math.max(1, ...roleEntries.map(([, count]) => count));
  const reports = data.community.reports;

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat
          icon={Users}
          label="Users"
          value={data.users.total}
          hint={`${data.users.active} active · ${data.users.suspended} suspended`}
        />
        <Stat
          icon={FileCheck2}
          label="Published content"
          value={publishedTotal}
          hint="Live across tools, prompts, workflows, templates"
        />
        <Stat
          icon={Users2}
          label="Communities"
          value={data.community.communities}
          hint={`${data.community.memberships.toLocaleString()} memberships`}
        />
        <Stat
          icon={Flag}
          label="Open reports"
          value={reports.open}
          hint={`${reports.reviewing} reviewing`}
          emphasis={reports.open > 0}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Content by state</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-3">
              {CONTENT_TYPES.map((type) => {
                const counts = data.content[type.key];
                const total =
                  counts.draft +
                  counts.in_review +
                  counts.published +
                  counts.archived;

                return (
                  <div key={type.key} className="space-y-1.5">
                    <div className="flex items-center justify-between text-caption">
                      <span className="font-medium text-text-secondary">
                        {type.label}
                      </span>
                      <span className="tabular-nums text-text-muted">
                        {total}
                      </span>
                    </div>
                    <div className="flex h-2.5 overflow-hidden rounded-full bg-bg-canvas">
                      {total > 0
                        ? STATE_SEGMENTS.map((segment) => {
                            const count = counts[segment.key];

                            return count > 0 ? (
                              <div
                                key={segment.key}
                                className={segment.color}
                                style={{ width: `${(count / total) * 100}%` }}
                              />
                            ) : null;
                          })
                        : null}
                    </div>
                  </div>
                );
              })}
            </div>
            <ul className="flex flex-wrap gap-x-4 gap-y-1.5">
              {STATE_SEGMENTS.map((segment) => (
                <li
                  key={segment.key}
                  className="flex items-center gap-1.5 text-caption text-text-muted"
                >
                  <span
                    aria-hidden="true"
                    className={cn("size-2 rounded-full", segment.color)}
                  />
                  {segment.label}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Users by role</CardTitle>
          </CardHeader>
          <CardContent>
            {roleEntries.length === 0 ? (
              <p className="text-body text-text-muted">
                No role assignments recorded yet.
              </p>
            ) : (
              <ul className="space-y-3">
                {roleEntries.map(([role, count]) => (
                  <li key={role} className="space-y-1">
                    <div className="flex items-center justify-between text-caption">
                      <span className="text-text-secondary">
                        {keyLabel(role)}
                      </span>
                      <span className="tabular-nums text-text-muted">
                        {count}
                      </span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-bg-canvas">
                      <div
                        className="h-full rounded-full bg-brand-primary"
                        style={{ width: `${(count / maxRole) * 100}%` }}
                      />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Moderation queue</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              {(
                [
                  { label: "Open", value: reports.open, emphasis: true },
                  {
                    label: "Reviewing",
                    value: reports.reviewing,
                    emphasis: false,
                  },
                  {
                    label: "Actioned",
                    value: reports.actioned,
                    emphasis: false,
                  },
                  {
                    label: "Dismissed",
                    value: reports.dismissed,
                    emphasis: false,
                  },
                ] as const
              ).map((entry) => (
                <div
                  key={entry.label}
                  className="rounded-md border border-border-subtle bg-bg-canvas p-3"
                >
                  <p className="text-caption text-text-muted">{entry.label}</p>
                  <p
                    className={cn(
                      "text-h4 tabular-nums",
                      entry.emphasis && entry.value > 0
                        ? "text-status-deadline"
                        : "text-text-primary",
                    )}
                  >
                    {entry.value}
                  </p>
                </div>
              ))}
            </div>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-text-muted">
              <span className="flex items-center gap-1.5">
                <ShieldCheck aria-hidden="true" className="size-3.5" />
                Mentors: {data.mentors.verified} verified ·{" "}
                {data.mentors.unverified} unverified
              </span>
              <span className="flex items-center gap-1.5">
                <Activity aria-hidden="true" className="size-3.5" />
                {data.audit_event_count.toLocaleString()} audit events
              </span>
            </div>
          </CardContent>
        </Card>

        <AccessCard />
      </div>
    </div>
  );
}

function AccessCard() {
  const { session } = useSession();

  return (
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
            <p className="flex items-center gap-1.5 text-caption text-text-muted">
              <ShieldCheck aria-hidden="true" className="size-3.5" />
              Each module unlocks only for the capabilities you hold, and the
              API re-checks every action.
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
  );
}

export function ConsoleOverview() {
  const { session, can } = useSession();
  const showMetrics = can("audit.view-all");

  const overviewQuery = useQuery({
    queryKey: analyticsKeys.overview,
    queryFn: getOperationalOverview,
    enabled: showMetrics,
  });

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Console overview</h1>
        <p className="text-body-lg text-text-secondary">
          {session
            ? `Signed in as ${session.user.name}.`
            : "Loading your session…"}{" "}
          {showMetrics
            ? "A live snapshot of the platform, composed from real records."
            : "Your workspace for the modules your role can access."}
        </p>
      </header>

      {showMetrics ? (
        overviewQuery.isPending ? (
          <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {[0, 1, 2, 3].map((index) => (
                <Card key={index}>
                  <CardContent className="space-y-3 p-5">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-9 w-16" />
                    <Skeleton className="h-3 w-32" />
                  </CardContent>
                </Card>
              ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
              <Skeleton className="h-56 w-full rounded-lg" />
              <Skeleton className="h-56 w-full rounded-lg" />
            </div>
          </div>
        ) : overviewQuery.isError ? (
          <ErrorState
            title="Metrics could not load"
            description="The operational overview request could not be completed."
            onRetry={() => void overviewQuery.refetch()}
          />
        ) : (
          <OverviewMetrics data={overviewQuery.data} />
        )
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          <AccessCard />
        </div>
      )}
    </div>
  );
}
