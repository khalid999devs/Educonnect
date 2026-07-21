import { cn } from "@educonnect/ui";

import type { ActivityRhythmDay } from "@/lib/api/progress";
import {
  formatDayLabel,
  formatMinutes,
  formatWeekdayInitial,
  pluralize,
} from "./progress-format";

/**
 * Seven calendar days, each marked active or not.
 *
 * PRODUCT INVARIANT: this is a rhythm, not a streak. There is deliberately no
 * consecutive-day counter, no "best run", no flame, and no badge. Quiet days
 * are shown as quiet days and carry no penalty framing - a gap in the strip is
 * information, not a failure.
 */
function dayDescription(day: ActivityRhythmDay): string {
  if (!day.was_active) {
    return "No activity recorded";
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

  return parts.join(", ");
}

export type RhythmStripProps = {
  days: ActivityRhythmDay[];
  className?: string;
};

export function RhythmStrip({ days, className }: RhythmStripProps) {
  const activeCount = days.filter((day) => day.was_active).length;

  return (
    <div className={cn("space-y-3", className)}>
      <ul
        className="grid grid-cols-7 gap-2"
        aria-label="Activity over the last seven days"
      >
        {days.map((day) => {
          const description = dayDescription(day);

          return (
            <li key={day.date}>
              <div
                title={`${formatDayLabel(day.date)}: ${description}`}
                className={cn(
                  "flex flex-col items-center gap-1.5 rounded-md border px-1 py-2.5 transition-colors",
                  day.was_active
                    ? "border-brand-primary/30 bg-brand-primary/12"
                    : "border-border-subtle bg-bg-surface hover:border-border-strong",
                )}
              >
                <span
                  aria-hidden="true"
                  className={cn(
                    "text-caption tabular-nums",
                    day.was_active ? "text-brand-primary" : "text-text-muted",
                  )}
                >
                  {formatWeekdayInitial(day.date)}
                </span>
                <span
                  aria-hidden="true"
                  className={cn(
                    "size-2 rounded-full",
                    day.was_active ? "bg-brand-primary" : "bg-bg-interactive",
                  )}
                />
                <span className="sr-only">
                  {formatDayLabel(day.date)}: {description}
                </span>
              </div>
            </li>
          );
        })}
      </ul>
      <p className="text-caption text-text-muted">
        {activeCount === 0 ? (
          "No recorded activity in the last seven days. That is the honest reading, not a penalty."
        ) : (
          <>
            Activity recorded on{" "}
            <span className="tabular-nums">{activeCount}</span> of{" "}
            <span className="tabular-nums">{days.length}</span> days. Order and
            gaps carry no score.
          </>
        )}
      </p>
    </div>
  );
}
