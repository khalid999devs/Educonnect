"use client";

import { cn, Skeleton } from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";

import { getProgress, type ActivityRhythmDay } from "@/lib/api/progress";
import {
  formatDayLabel,
  formatMinutes,
  formatWeekdayInitial,
  pluralize,
} from "@/components/progress/progress-format";
import { progressKeys } from "@/lib/query-keys";

/**
 * Seven days of the student's own recorded activity, in the sidebar footer.
 *
 * PRODUCT INVARIANT (README.md, ADR-0018, and 13 other places): this is a
 * rhythm, not a streak. There is deliberately no consecutive-day counter, no
 * "best run", no flame, no badge, no percentile, and no "vs last week" delta.
 * A quiet week is reported as a quiet week, plainly and without judgement -
 * a gap in the strip is information, never a failure.
 *
 * This component renders on every authenticated page, so the read is
 * deliberately cheap and deliberately optional: one cached week-window call,
 * no retry, no refetch on focus. Anything other than a successful read renders
 * nothing at all. Navigation must never depend on it.
 */
function describeDay(day: ActivityRhythmDay): string {
  if (!day.was_active) {
    return "no activity recorded";
  }

  const parts: string[] = [];

  if (day.signals.tasks > 0) {
    parts.push(pluralize(day.signals.tasks, "task", "tasks"));
  }

  if (day.signals.focus_minutes > 0) {
    parts.push(`${formatMinutes(day.signals.focus_minutes)} focus`);
  }

  if (day.signals.resources > 0) {
    parts.push(pluralize(day.signals.resources, "resource", "resources"));
  }

  if (day.signals.notes > 0) {
    parts.push(pluralize(day.signals.notes, "note", "notes"));
  }

  return parts.length > 0 ? parts.join(", ") : "activity recorded";
}

export type SidebarRhythmStripProps = {
  days: ActivityRhythmDay[];
};

/** Presentation only, so the invariant can be tested without a query client.
 *
 * Chrome, not a card: it sits inside the sidebar footer's own border, so it
 * carries no boundary and no summary sentence of its own - just the label and
 * the seven dots, kept small. The per-day story stays in the screen-reader
 * text, where the honest, no-judgement wording still lives. */
export function SidebarRhythmStrip({ days }: SidebarRhythmStripProps) {
  return (
    <section aria-labelledby="sidebar-rhythm-heading" className="space-y-2">
      <h2
        id="sidebar-rhythm-heading"
        className="text-caption font-medium text-text-muted"
      >
        Your rhythm
      </h2>

      <ul className="grid grid-cols-7 gap-1">
        {days.map((day) => (
          <li key={day.date} className="flex flex-col items-center gap-1">
            <span
              aria-hidden="true"
              className="text-caption tabular-nums text-text-muted"
            >
              {formatWeekdayInitial(day.date)}
            </span>
            <span
              aria-hidden="true"
              className={cn(
                "size-2 rounded-full transition-colors",
                day.was_active ? "bg-status-research" : "bg-bg-interactive",
              )}
            />
            <span className="sr-only">
              {formatDayLabel(day.date)}: {describeDay(day)}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}

export function SidebarRhythm() {
  const timezone = useMemo(
    () => Intl.DateTimeFormat().resolvedOptions().timeZone,
    [],
  );

  const query = useQuery({
    queryKey: progressKeys.overview(timezone, "week"),
    queryFn: () => getProgress({ timezone, window: "week" }),
    /* Sidebar chrome: never retry, never refetch on focus, never surface an
     * error boundary. A failed read is a rendered nothing, not a broken nav. */
    retry: false,
    throwOnError: false,
    refetchOnWindowFocus: false,
    staleTime: 5 * 60 * 1000,
    gcTime: 30 * 60 * 1000,
  });

  if (query.isPending) {
    return (
      <div aria-hidden="true" className="space-y-2">
        <Skeleton className="h-3 w-20" />
        <ul className="grid grid-cols-7 gap-1">
          {[0, 1, 2, 3, 4, 5, 6].map((slot) => (
            <li key={slot} className="flex justify-center">
              <span className="size-2 rounded-full bg-bg-interactive" />
            </li>
          ))}
        </ul>
      </div>
    );
  }

  const days = query.data?.activity_rhythm.days;

  if (query.isError || days === undefined || days.length === 0) {
    return null;
  }

  return <SidebarRhythmStrip days={days} />;
}
