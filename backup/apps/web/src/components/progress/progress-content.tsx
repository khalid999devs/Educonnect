"use client";

import {
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
} from "@educonnect/ui";
import {
  ArrowRight,
  Boxes,
  Brain,
  CalendarRange,
  CircleCheck,
  Clock,
  FileText,
  FolderOpen,
  Inbox,
  LayoutTemplate,
  ListChecks,
  Microscope,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import Link from "next/link";

import { IconChip } from "@/components/shared/icon-chip";
import { ProgressRing } from "@/components/shared/progress-ring";
import { StatTile } from "@/components/shared/stat-tile";
import type { SectionAccentKey } from "@/components/shell/section-accent";
import type { Progress } from "@/lib/api/progress";
import {
  ACTIVITY_METRIC_LABELS,
  ActivityChart,
  type ActivityMetric,
} from "./activity-chart";
import { buildMilestones, MilestoneFeed } from "./milestone-feed";
import {
  formatDateLabel,
  formatDayLabel,
  formatMinutes,
} from "./progress-format";
import { RhythmStrip } from "./rhythm-strip";

/**
 * The Progress read model rendered as a page body.
 *
 * PRODUCT INVARIANT (README, ADR-0018, and 13 other places in the repo): every
 * figure here is a real count from the student's own records. This component
 * must never render a streak, a consecutive-day counter, a flame, a badge, a
 * percentile, or a "vs last week" delta - the backend refuses to compute them
 * and the presentation layer must not reintroduce them. An empty window says
 * so plainly. `progress-content.test.tsx` asserts all of this.
 */
const METRIC_ORDER: ActivityMetric[] = [
  "tasks_completed",
  "focus_minutes",
  "resources_added",
  "notes_written",
];

const WINDOW_NOUN: Record<Progress["timeframe"]["window"], string> = {
  week: "this week",
  month: "this month",
  term: "in this window",
};

type SummaryRow = {
  icon: LucideIcon;
  accent: SectionAccentKey;
  label: string;
  value: string;
};

export type ProgressContentProps = {
  progress: Progress;
  metric: ActivityMetric;
  onMetricChange: (metric: ActivityMetric) => void;
};

export function ProgressContent({
  progress,
  metric,
  onMetricChange,
}: ProgressContentProps) {
  const { timeframe, totals, daily, activity_rhythm: rhythm } = progress;
  const windowNoun = WINDOW_NOUN[timeframe.window];
  const milestones = buildMilestones(daily);

  const completionPercent =
    totals.tasks_due > 0
      ? Math.min(100, (totals.tasks_completed / totals.tasks_due) * 100)
      : 0;

  const summaryRows: SummaryRow[] = [
    {
      icon: CircleCheck,
      accent: "community",
      label: "Tasks completed",
      value: `${totals.tasks_completed} / ${totals.tasks_due}`,
    },
    {
      icon: Clock,
      accent: "progress",
      label: "Focus time",
      value: formatMinutes(totals.focus_minutes),
    },
    {
      icon: FolderOpen,
      accent: "resources",
      label: "Resources added",
      value: String(totals.resources_added),
    },
    {
      icon: Brain,
      accent: "secondBrain",
      label: "Notes written",
      value: String(totals.notes_written),
    },
  ];

  return (
    <div className="space-y-4">
      <section className="motion-safe:animate-fade-up grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
        <Card>
          <CardHeader className="mb-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <CardTitle as="h2">Progress summary</CardTitle>
              <Badge variant="neutral">
                <span className="tabular-nums">
                  {formatDateLabel(timeframe.starts_on)} to{" "}
                  {formatDateLabel(timeframe.ends_on)}
                </span>
              </Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-center gap-6">
              {totals.tasks_due > 0 ? (
                <ProgressRing
                  percent={completionPercent}
                  label={`of the ${totals.tasks_due} tasks due ${windowNoun} are completed`}
                  caption="tasks due"
                  accent="progress"
                />
              ) : (
                <div className="flex size-28 shrink-0 flex-col items-center justify-center rounded-full border border-border-subtle bg-bg-surface px-4 text-center">
                  <span className="text-caption leading-tight text-text-muted">
                    No tasks were due {windowNoun}
                  </span>
                </div>
              )}
              <ul className="min-w-0 flex-1 space-y-2.5">
                {summaryRows.map((row) => (
                  <li
                    key={row.label}
                    className="flex items-center gap-3 rounded-md border border-border-subtle px-3.5 py-2.5 transition-colors hover:border-border-strong"
                  >
                    <IconChip icon={row.icon} accent={row.accent} size="sm" />
                    <span className="flex-1 text-body text-text-secondary">
                      {row.label}
                    </span>
                    <span className="text-body font-semibold tabular-nums text-text-primary">
                      {row.value}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
            <p className="text-body text-text-secondary">{progress.summary}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="mb-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <CardTitle as="h2">Milestones: real events</CardTitle>
              <Badge variant="brand">Recorded, not scored</Badge>
            </div>
          </CardHeader>
          <CardContent>
            <MilestoneFeed milestones={milestones} />
          </CardContent>
        </Card>
      </section>

      <section className="motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]">
        <Card>
          <CardHeader className="mb-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <CardTitle as="h2">Activity by day</CardTitle>
              <div
                role="group"
                aria-label="Choose which signal to chart"
                className="flex flex-wrap gap-1"
              >
                {METRIC_ORDER.map((option) => {
                  const selected = option === metric;

                  return (
                    <button
                      key={option}
                      type="button"
                      aria-pressed={selected}
                      onClick={() => onMetricChange(option)}
                      className={cn(
                        "rounded-md border px-2.5 py-1.5 text-caption transition-colors",
                        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                        selected
                          ? "border-brand-primary/30 bg-brand-primary/12 text-brand-primary"
                          : "border-border-subtle text-text-muted hover:border-border-strong hover:text-text-primary",
                      )}
                    >
                      {ACTIVITY_METRIC_LABELS[option]}
                    </button>
                  );
                })}
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            <ActivityChart days={daily} metric={metric} />
            <p className="text-body text-text-secondary">
              {progress.has_activity
                ? `Each point is a day in this window, drawn from your own records. There is no target line and no comparison against anyone else or any other period.`
                : `Nothing was recorded ${windowNoun}. The line sits at zero because that is what happened, and an empty window is not a setback.`}
            </p>
          </CardContent>
        </Card>
      </section>

      <section className="scroll-reveal grid gap-4 lg:grid-cols-[0.95fr_1.05fr]">
        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h2">Last seven days</CardTitle>
          </CardHeader>
          <CardContent>
            <RhythmStrip days={rhythm.days} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h2">One next action</CardTitle>
          </CardHeader>
          <CardContent>
            {progress.next_action === null ? (
              <p className="text-body text-text-secondary">
                Nothing is waiting on you right now. When a task comes due or a
                capture needs review, exactly one next action appears here.
              </p>
            ) : (
              <Link
                href={
                  progress.next_action.kind === "task"
                    ? "/planner"
                    : "/second-brain"
                }
                className={cn(
                  "flex items-center gap-3 rounded-md border border-border-subtle px-3.5 py-3",
                  "transition-colors hover:border-border-strong",
                  "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                )}
              >
                <IconChip
                  icon={
                    progress.next_action.kind === "task" ? ListChecks : Inbox
                  }
                  accent={
                    progress.next_action.kind === "task"
                      ? "planner"
                      : "secondBrain"
                  }
                  size="md"
                />
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-body font-medium text-text-primary">
                    {progress.next_action.title}
                  </span>
                  <span className="block text-caption tabular-nums text-text-muted">
                    {progress.next_action.kind === "task"
                      ? progress.next_action.due_at === null
                        ? "Task with no due date"
                        : `Due ${formatDayLabel(progress.next_action.due_at.slice(0, 10))}`
                      : "Waiting for your review"}
                  </span>
                </span>
                <ArrowRight
                  aria-hidden="true"
                  className="size-4 shrink-0 text-text-muted"
                />
              </Link>
            )}
          </CardContent>
        </Card>
      </section>

      <section className="scroll-reveal">
        <Card>
          <CardHeader className="mb-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <CardTitle as="h2">Everything counted {windowNoun}</CardTitle>
              {timeframe.term_label ? (
                <Badge variant="neutral">{timeframe.term_label}</Badge>
              ) : null}
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <StatTile
                icon={CircleCheck}
                accent="community"
                label="Tasks completed"
                value={String(totals.tasks_completed)}
              />
              <StatTile
                icon={CalendarRange}
                accent="planner"
                label="Tasks due"
                value={String(totals.tasks_due)}
              />
              <StatTile
                icon={Clock}
                accent="progress"
                label="Focus minutes"
                value={String(totals.focus_minutes)}
              />
              <StatTile
                icon={FolderOpen}
                accent="resources"
                label="Resources added"
                value={String(totals.resources_added)}
              />
              <StatTile
                icon={Inbox}
                accent="secondBrain"
                label="Captures processed"
                value={String(totals.intake_items_processed)}
              />
              <StatTile
                icon={Boxes}
                accent="secondBrain"
                label="Knowledge items"
                value={String(totals.knowledge_items_added)}
              />
              <StatTile
                icon={FileText}
                accent="secondBrain"
                label="Notes written"
                value={String(totals.notes_written)}
              />
              <StatTile
                icon={LayoutTemplate}
                accent="templates"
                label="Template copies"
                value={String(totals.template_copies_created)}
              />
              <StatTile
                icon={Microscope}
                accent="study"
                label="Sources reviewed"
                value={String(totals.research_sources_reviewed)}
              />
            </div>
          </CardContent>
        </Card>
      </section>
    </div>
  );
}
