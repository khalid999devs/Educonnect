"use client";

import { cn } from "@educonnect/ui";

import type { ProgressDay } from "@/lib/api/progress";
import {
  formatMinutes,
  formatShortDate,
  formatWeekdayInitial,
} from "./progress-format";

/**
 * Area chart of one real daily signal across the selected window.
 *
 * Accessibility (build brief 6.4): `role="img"` with a data-bearing
 * `aria-label` that names every plotted value, so a screen reader gets the
 * series itself rather than the word "image". A window with no activity at all
 * still renders its flat zero baseline and says so in the label - it is never
 * padded with invented numbers.
 */
export type ActivityMetric =
  "tasks_completed" | "focus_minutes" | "resources_added" | "notes_written";

export const ACTIVITY_METRIC_LABELS: Record<ActivityMetric, string> = {
  tasks_completed: "Tasks completed",
  focus_minutes: "Focus minutes",
  resources_added: "Resources added",
  notes_written: "Notes written",
};

const VIEW_WIDTH = 640;
const VIEW_HEIGHT = 148;
const PAD_X = 14;
const TOP = 14;
const BASELINE = 116;

function describe(metric: ActivityMetric, value: number): string {
  return metric === "focus_minutes"
    ? formatMinutes(value)
    : String(Math.round(value));
}

export type ActivityChartProps = {
  days: ProgressDay[];
  metric: ActivityMetric;
  className?: string;
};

export function ActivityChart({ days, metric, className }: ActivityChartProps) {
  if (days.length === 0) {
    return null;
  }

  const values = days.map((day) => day[metric]);
  const peak = Math.max(...values);
  const scale = peak > 0 ? peak : 1;
  const step =
    days.length === 1 ? 0 : (VIEW_WIDTH - PAD_X * 2) / (days.length - 1);

  const points = values.map((value, index) => {
    const x = days.length === 1 ? VIEW_WIDTH / 2 : PAD_X + index * step;
    const y = BASELINE - (value / scale) * (BASELINE - TOP);

    return [x, y] as const;
  });

  const line = points
    .map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`)
    .join(" ");
  const first = points[0]!;
  const last = points[points.length - 1]!;

  /* Dense windows get sparser tick labels so the axis never collides. */
  const tickEvery = Math.ceil(days.length / 10);
  const useWeekdays = days.length <= 8;

  const summary = days
    .map(
      (day, index) =>
        `${formatShortDate(day.date)}: ${describe(metric, values[index]!)}`,
    )
    .join(", ");

  const label =
    peak === 0
      ? `${ACTIVITY_METRIC_LABELS[metric]} by day: no activity recorded on any of these ${days.length} days.`
      : `${ACTIVITY_METRIC_LABELS[metric]} by day. ${summary}.`;

  const gradientId = `progress-area-${metric}`;

  return (
    <svg
      viewBox={`0 0 ${VIEW_WIDTH} ${VIEW_HEIGHT}`}
      role="img"
      aria-label={label}
      className={cn("w-full", className)}
    >
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop
            offset="0"
            stopColor="var(--brand-primary)"
            stopOpacity="0.32"
          />
          <stop offset="1" stopColor="var(--brand-primary)" stopOpacity="0" />
        </linearGradient>
      </defs>

      <line
        x1={PAD_X}
        y1={BASELINE}
        x2={VIEW_WIDTH - PAD_X}
        y2={BASELINE}
        stroke="var(--border-subtle)"
        strokeWidth="1"
      />

      {peak > 0 ? (
        <polygon
          points={`${first[0].toFixed(1)},${BASELINE} ${line} ${last[0].toFixed(1)},${BASELINE}`}
          fill={`url(#${gradientId})`}
        />
      ) : null}

      <polyline
        points={line}
        fill="none"
        stroke="var(--brand-primary)"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />

      {points.map(([x, y], index) =>
        values[index]! > 0 ? (
          <circle
            key={`point-${days[index]!.date}`}
            cx={x}
            cy={y}
            r="3.5"
            fill="var(--brand-focus)"
          />
        ) : null,
      )}

      {points.map(([x], index) =>
        index % tickEvery === 0 || index === days.length - 1 ? (
          <text
            key={`tick-${days[index]!.date}`}
            x={x}
            y={BASELINE + 20}
            textAnchor="middle"
            fontSize="13"
            fill="var(--text-muted)"
          >
            {useWeekdays
              ? formatWeekdayInitial(days[index]!.date)
              : formatShortDate(days[index]!.date)}
          </text>
        ) : null,
      )}
    </svg>
  );
}
