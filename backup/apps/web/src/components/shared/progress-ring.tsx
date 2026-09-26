import { cn } from "@educonnect/ui";

import type { SectionAccentKey } from "@/components/shell/section-accent";

/**
 * Conic-gradient progress ring, matching the demo dashboard idiom.
 *
 * `role="img"` with a data-bearing `aria-label` (build brief 6.4): screen
 * readers get the number and what it measures, not "image". The ring reports a
 * real completed-out-of-total figure - it is never a streak, a badge, or a
 * comparison against a previous period.
 */
const TRACK_COLOR: Record<SectionAccentKey, string> = {
  home: "var(--brand-primary)",
  secondBrain: "var(--status-research)",
  study: "var(--status-ai)",
  planner: "var(--status-deadline)",
  resources: "var(--status-info)",
  aiTools: "var(--status-ai)",
  templates: "var(--status-info)",
  community: "var(--status-success)",
  progress: "var(--brand-primary)",
  settings: "var(--text-muted)",
};

export type ProgressRingProps = {
  /** Clamped to 0-100. */
  percent: number;
  /** What the percentage measures, e.g. "of this week's tasks completed". */
  label: string;
  /** Short text inside the ring beneath the number. */
  caption?: string;
  accent?: SectionAccentKey;
  size?: "md" | "lg";
  className?: string;
};

const SIZES = {
  md: { outer: "size-20", inner: "size-15", value: "text-h4" },
  lg: { outer: "size-28", inner: "size-22", value: "text-h3" },
} as const;

export function ProgressRing({
  percent,
  label,
  caption,
  accent = "progress",
  size = "lg",
  className,
}: ProgressRingProps) {
  const safe = Math.max(0, Math.min(100, Math.round(percent)));
  const dimensions = SIZES[size];

  return (
    <div
      role="img"
      aria-label={`${safe} percent ${label}`}
      className={cn(
        "relative flex shrink-0 items-center justify-center rounded-full",
        dimensions.outer,
        className,
      )}
      style={{
        background: `conic-gradient(${TRACK_COLOR[accent]} ${safe * 3.6}deg, var(--bg-interactive) 0deg)`,
      }}
    >
      <span
        className={cn(
          "flex flex-col items-center justify-center rounded-full bg-bg-surface text-center",
          dimensions.inner,
        )}
      >
        <span
          aria-hidden="true"
          className={cn("tabular-nums text-text-primary", dimensions.value)}
        >
          {safe}%
        </span>
        {caption ? (
          <span
            aria-hidden="true"
            className="px-2 text-caption leading-tight text-text-muted"
          >
            {caption}
          </span>
        ) : null}
      </span>
    </div>
  );
}
