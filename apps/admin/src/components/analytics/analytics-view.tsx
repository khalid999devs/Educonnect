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
  getOperationalTelemetry,
  type OperationalOverview,
  type OperationalTelemetry,
} from "@/lib/api/admin-analytics";
import { humanizeKey } from "@/lib/format";
import { analyticsKeys } from "@/lib/query-keys";

export function AnalyticsView() {
  const query = useQuery({
    queryKey: analyticsKeys.overview,
    queryFn: getOperationalOverview,
  });
  const telemetry = useQuery({
    queryKey: analyticsKeys.telemetry,
    queryFn: getOperationalTelemetry,
    refetchInterval: 30_000,
  });

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Analytics</h1>
        <p className="text-body text-text-secondary">
          A privacy-safe operational overview — aggregate counts and operational
          telemetry only, never prompts, completions, or private academic
          content.
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

      {telemetry.isPending ? (
        <div className="grid gap-4 md:grid-cols-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={`t-${index}`} className="h-40 w-full" />
          ))}
        </div>
      ) : telemetry.isError ? (
        <ErrorState
          title="Could not load telemetry"
          description="Operational telemetry could not be loaded."
          onRetry={() => void telemetry.refetch()}
        />
      ) : (
        <Telemetry telemetry={telemetry.data} />
      )}
    </div>
  );
}

function formatLatency(value: number | null): string {
  return value === null ? "—" : `${value} ms`;
}

function formatRate(value: number): string {
  return `${(value * 100).toFixed(1)}%`;
}

function Telemetry({ telemetry }: { telemetry: OperationalTelemetry }) {
  const { ai, jobs, errors, http } = telemetry;

  return (
    <div className="space-y-3">
      <div className="flex items-baseline justify-between">
        <h2 className="text-h3 text-text-primary">Operational telemetry</h2>
        <p className="text-caption text-text-muted">
          Last {telemetry.window_hours}h · AI, jobs & errors are durable; HTTP
          is a rolling {Math.round(http.window_seconds / 60)}-min estimate
        </p>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>AI providers</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {ai.total === 0 ? (
              <p className="text-body text-text-muted">
                No AI-provider calls in this window.
              </p>
            ) : (
              <>
                <Stat label="Calls" value={ai.total} />
                <Stat label="Succeeded" value={ai.by_outcome.success} />
                <Stat label="Failed" value={ai.by_outcome.failure} />
                <Stat
                  label="Fell back to deterministic"
                  value={ai.by_outcome.fallback}
                />
                <TextStat
                  label="Fallback rate"
                  value={formatRate(ai.fallback_rate)}
                />
                <TextStat
                  label="Latency p95"
                  value={formatLatency(ai.latency_ms.p95)}
                />
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Background jobs</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {jobs.total === 0 ? (
              <p className="text-body text-text-muted">
                No intake jobs ran in this window.
              </p>
            ) : (
              <>
                <Stat label="Runs" value={jobs.total} />
                <Stat label="Succeeded" value={jobs.by_outcome.success} />
                <Stat label="Failed" value={jobs.by_outcome.failure} />
                <TextStat
                  label="Failure rate"
                  value={formatRate(jobs.failure_rate)}
                />
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>HTTP latency</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {http.request_count === 0 ? (
              <p className="text-body text-text-muted">
                No requests sampled in this window yet.
              </p>
            ) : (
              <>
                <Stat label="Requests" value={http.request_count} />
                <TextStat
                  label="Error rate (5xx)"
                  value={formatRate(http.error_rate)}
                />
                <TextStat
                  label="Latency p50"
                  value={formatLatency(http.latency_ms.p50)}
                />
                <TextStat
                  label="Latency p95"
                  value={formatLatency(http.latency_ms.p95)}
                />
                <TextStat
                  label="Latency p99"
                  value={formatLatency(http.latency_ms.p99)}
                />
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Captured server errors</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {errors.total === 0 ? (
              <p className="text-body text-text-muted">
                No server faults captured in this window.
              </p>
            ) : (
              <>
                <Stat label="Total" value={errors.total} />
                <div className="pt-2">
                  <p className="mb-1 text-caption font-medium uppercase tracking-wide text-text-muted">
                    By code
                  </p>
                  {Object.entries(errors.by_code).map(([code, count]) => (
                    <Stat key={code} label={humanizeKey(code)} value={count} />
                  ))}
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function TextStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between text-body">
      <span className="text-text-secondary">{label}</span>
      <span className="font-semibold text-text-primary">{value}</span>
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
