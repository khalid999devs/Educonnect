import { cn } from "@educonnect/ui";
import { CircleCheck, Clock, FileText, FolderOpen } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import type { SectionAccentKey } from "@/components/shell/section-accent";
import type { ProgressDay } from "@/lib/api/progress";
import { formatDayLabel, formatMinutes, pluralize } from "./progress-format";

/**
 * Milestone feed, ported from the Live Demo's ProgressView design onto real
 * records.
 *
 * Every entry is a genuine per-day figure the API already returned; nothing is
 * synthesised, ranked, or celebrated. There is no "personal best", no
 * comparison against another period, and no badge - a milestone here is simply
 * "this happened, on this day".
 */
export type Milestone = {
  id: string;
  icon: LucideIcon;
  accent: SectionAccentKey;
  title: string;
  meta: string;
};

/** Newest first, capped so a term window cannot render hundreds of rows. */
export function buildMilestones(days: ProgressDay[], limit = 12): Milestone[] {
  const milestones: Milestone[] = [];

  for (const day of [...days].reverse()) {
    const label = formatDayLabel(day.date);

    if (day.tasks_completed > 0) {
      milestones.push({
        id: `${day.date}-tasks`,
        icon: CircleCheck,
        accent: "community",
        title: `${pluralize(day.tasks_completed, "task", "tasks")} completed`,
        meta: label,
      });
    }

    if (day.focus_minutes > 0) {
      milestones.push({
        id: `${day.date}-focus`,
        icon: Clock,
        accent: "progress",
        title: `${formatMinutes(day.focus_minutes)} of focus logged`,
        meta: label,
      });
    }

    if (day.resources_added > 0) {
      milestones.push({
        id: `${day.date}-resources`,
        icon: FolderOpen,
        accent: "resources",
        title: `${pluralize(day.resources_added, "resource", "resources")} filed`,
        meta: label,
      });
    }

    if (day.notes_written > 0) {
      milestones.push({
        id: `${day.date}-notes`,
        icon: FileText,
        accent: "secondBrain",
        title: `${pluralize(day.notes_written, "note", "notes")} written`,
        meta: label,
      });
    }
  }

  return milestones.slice(0, limit);
}

/* Static literal classes: Tailwind v4 scans source text, so a computed delay
   class would silently produce nothing. */
const STAGGER = [
  "motion-safe:[animation-delay:0ms]",
  "motion-safe:[animation-delay:80ms]",
  "motion-safe:[animation-delay:160ms]",
  "motion-safe:[animation-delay:240ms]",
  "motion-safe:[animation-delay:320ms]",
] as const;

export type MilestoneFeedProps = {
  milestones: Milestone[];
};

export function MilestoneFeed({ milestones }: MilestoneFeedProps) {
  if (milestones.length === 0) {
    return (
      <p className="text-body text-text-secondary">
        Nothing recorded in this window yet. Complete a task, run a focus
        session, file a resource or write a note and it appears here exactly as
        it happened. Nothing is invented to fill the space.
      </p>
    );
  }

  return (
    <ul className="space-y-2.5">
      {milestones.map((milestone, index) => (
        <li
          key={milestone.id}
          className={cn(
            "flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3",
            "transition-colors hover:border-border-strong",
            "motion-safe:animate-fade-up",
            STAGGER[Math.min(index, STAGGER.length - 1)],
          )}
        >
          <IconChip
            icon={milestone.icon}
            accent={milestone.accent}
            size="md"
            round
          />
          <span className="min-w-0">
            <span className="block truncate text-body font-medium text-text-primary">
              {milestone.title}
            </span>
            <span className="block text-caption tabular-nums text-text-muted">
              {milestone.meta}
            </span>
          </span>
        </li>
      ))}
    </ul>
  );
}
