"use client";

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";

import {
  getOperationalOverview,
  type OperationalOverview,
} from "@/lib/api/admin-analytics";
import { humanizeKey } from "@/lib/format";
import { analyticsKeys } from "@/lib/query-keys";

export function AnalyticsView() {
  const query = useQuery({
    queryKey: analyticsKeys.overview,
    queryFn: getOperationalOverview,
  });

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Analytics</h1>
        <p className="text-body text-text-secondary">
          A privacy-safe operational overview — aggregate counts only, never
          private academic content. AI-usage, job-health, and error telemetry
          arrive with the hardening phase.
        </p>
      </header>

      {query.isPending ? (
        <div className="grid gap-4 md:grid-cols-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-40 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load analytics"
          description="The operational overview could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : (
        <Overview overview={query.data} />
      )}
    </div>
  );
}

function Overview({ overview }: { overview: OperationalOverview }) {
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Users</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <Stat label="Total" value={overview.users.total} />
          <Stat label="Active" value={overview.users.active} />
          <Stat label="Suspended" value={overview.users.suspended} />
          <div className="pt-2">
            <p className="mb-1 text-caption font-medium uppercase tracking-wide text-text-muted">
              By role
            </p>
            {Object.entries(overview.users.by_role).map(([role, count]) => (
              <Stat key={role} label={humanizeKey(role)} value={count} />
            ))}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Community</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <Stat
            label="Published communities"
            value={overview.community.communities}
          />
          <Stat label="Memberships" value={overview.community.memberships} />
          <Stat label="Verified mentors" value={overview.mentors.verified} />
          <Stat
            label="Unverified mentors"
            value={overview.mentors.unverified}
          />
          <div className="pt-2">
            <p className="mb-1 text-caption font-medium uppercase tracking-wide text-text-muted">
              Report backlog
            </p>
            <Stat label="Open" value={overview.community.reports.open} />
            <Stat
              label="Reviewing"
              value={overview.community.reports.reviewing}
            />
            <Stat
              label="Actioned"
              value={overview.community.reports.actioned}
            />
            <Stat
              label="Dismissed"
              value={overview.community.reports.dismissed}
            />
          </div>
        </CardContent>
      </Card>

      <Card className="md:col-span-2">
        <CardHeader>
          <CardTitle>Content catalog by state</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-body">
              <thead>
                <tr className="border-b border-border-subtle text-caption uppercase tracking-wide text-text-muted">
                  <th className="px-3 py-2 font-medium">Type</th>
                  <th className="px-3 py-2 font-medium">Draft</th>
                  <th className="px-3 py-2 font-medium">In review</th>
                  <th className="px-3 py-2 font-medium">Published</th>
                  <th className="px-3 py-2 font-medium">Archived</th>
                </tr>
              </thead>
              <tbody>
                {(["tools", "prompts", "workflows", "templates"] as const).map(
                  (type) => (
                    <tr
                      key={type}
                      className="border-b border-border-subtle last:border-0"
                    >
                      <td className="px-3 py-2 font-medium text-text-primary">
                        {humanizeKey(type)}
                      </td>
                      <td className="px-3 py-2">
                        {overview.content[type].draft}
                      </td>
                      <td className="px-3 py-2">
                        {overview.content[type].in_review}
                      </td>
                      <td className="px-3 py-2">
                        {overview.content[type].published}
                      </td>
                      <td className="px-3 py-2">
                        {overview.content[type].archived}
                      </td>
                    </tr>
                  ),
                )}
              </tbody>
            </table>
          </div>
          <p className="mt-3 text-caption text-text-muted">
            {overview.audit_event_count} audit events recorded.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex items-center justify-between text-body">
      <span className="text-text-secondary">{label}</span>
      <span className="font-semibold text-text-primary">{value}</span>
    </div>
  );
}
