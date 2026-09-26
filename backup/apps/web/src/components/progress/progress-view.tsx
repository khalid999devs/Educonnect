"use client";

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import { CalendarRange, LineChart, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";

import { PageCover } from "@/components/shared/page-cover";
import { SectionTabs } from "@/components/shared/section-tabs";
import { getProgress, type ProgressWindow } from "@/lib/api/progress";
import { progressKeys } from "@/lib/query-keys";
import type { ActivityMetric } from "./activity-chart";
import { ProgressContent } from "./progress-content";
import { RhythmStrip } from "./rhythm-strip";

/**
 * The Progress section.
 *
 * Timezone is browser-derived on every read, exactly like the dashboard and
 * planner calls: there is no persisted timezone and this surface must not add
 * one (build brief 5.4).
 *
 * PRODUCT INVARIANT: real records only. No streak, no consecutive-day counter,
 * no flame, no badge, no percentile, no "vs last week" delta. When the window
 * is empty the page says so in plain language instead of dressing zero up as
 * encouragement.
 */
const WINDOW_TABS = [
  { value: "week" as const, label: "This week" },
  { value: "month" as const, label: "This month" },
  { value: "term" as const, label: "This term" },
];

export function ProgressView() {
  const [activeWindow, setActiveWindow] = useState<ProgressWindow>("week");
  const [metric, setMetric] = useState<ActivityMetric>("tasks_completed");

  const timezone = useMemo(
    () => Intl.DateTimeFormat().resolvedOptions().timeZone,
    [],
  );

  const query = useQuery({
    queryKey: progressKeys.overview(timezone, activeWindow),
    queryFn: () => getProgress({ timezone, window: activeWindow }),
  });

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/notebook-pens.jpg"
        title="Real momentum only"
        subtitle="Every number here is computed from your own records. No streaks, no badges, no percentiles, and no comparison against last week."
        headingLevel={1}
        tall
        priority
      />

      <SectionTabs
        tabs={WINDOW_TABS}
        value={activeWindow}
        onChange={setActiveWindow}
        label="Progress window"
        panelId={() => "progress-panel"}
      />

      <div id="progress-panel">
        {query.isPending ? (
          <ProgressSkeleton />
        ) : query.isError ? (
          <ErrorState
            title="Progress could not be loaded"
            description="Your records are safe. The figures could not be computed just now."
            onRetry={() => {
              void query.refetch();
            }}
          />
        ) : !query.data.has_activity ? (
          <div className="space-y-4">
            <EmptyState
              icon={LineChart}
              title="Nothing recorded in this window"
              description="This window is genuinely empty, so there is nothing to show. Complete a task, run a focus session, file a resource or write a note and it will appear here exactly as it happened."
            />
            <Card>
              <CardHeader className="mb-3">
                <CardTitle as="h2">Last seven days</CardTitle>
              </CardHeader>
              <CardContent>
                <RhythmStrip days={query.data.activity_rhythm.days} />
              </CardContent>
            </Card>
          </div>
        ) : (
          <ProgressContent
            progress={query.data}
            metric={metric}
            onMetricChange={setMetric}
          />
        )}
      </div>
    </div>
  );
}

function ProgressSkeleton() {
  return (
    <div className="space-y-4" aria-hidden="true">
      <div className="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center gap-2">
              <CalendarRange className="size-4 text-text-muted" />
              <Skeleton className="h-4 w-40" />
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            <Skeleton className="size-28 rounded-full" />
            <Skeleton className="h-11 w-full" />
            <Skeleton className="h-11 w-full" />
            <Skeleton className="h-11 w-full" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center gap-2">
              <Sparkles className="size-4 text-text-muted" />
              <Skeleton className="h-4 w-40" />
            </div>
          </CardHeader>
          <CardContent className="space-y-2.5">
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </CardContent>
        </Card>
      </div>
      <Card>
        <CardContent>
          <Skeleton className="h-36 w-full" />
        </CardContent>
      </Card>
    </div>
  );
}
