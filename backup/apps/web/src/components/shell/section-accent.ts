/**
 * Section accent map (build brief 6.2).
 *
 * Decoration only: accent surfaces as chip fill, icon glyph, and optional
 * border - never as a page background or body text color, so WCAG AA contrast
 * is untouched. `status-error` and `status-warning` are deliberately absent:
 * they carry meaning (failure / caution) and must never read as decoration.
 *
 * Every value is a STATIC LITERAL class string. Tailwind v4 scans source text,
 * so template-literal class construction silently produces nothing.
 */
export type SectionAccent = {
  chip: string;
  icon: string;
  ring: string;
  dot: string;
};

export type SectionAccentKey =
  | "home"
  | "secondBrain"
  | "study"
  | "planner"
  | "resources"
  | "aiTools"
  | "templates"
  | "community"
  | "progress"
  | "settings";

export const SECTION_ACCENT: Record<SectionAccentKey, SectionAccent> = {
  home: {
    chip: "bg-brand-primary/12",
    icon: "text-brand-primary",
    ring: "border-brand-primary/30",
    dot: "bg-brand-primary",
  },
  secondBrain: {
    chip: "bg-status-research/12",
    icon: "text-status-research",
    ring: "border-status-research/30",
    dot: "bg-status-research",
  },
  study: {
    chip: "bg-status-ai/12",
    icon: "text-status-ai",
    ring: "border-status-ai/30",
    dot: "bg-status-ai",
  },
  planner: {
    chip: "bg-status-deadline/12",
    icon: "text-status-deadline",
    ring: "border-status-deadline/30",
    dot: "bg-status-deadline",
  },
  resources: {
    chip: "bg-status-info/12",
    icon: "text-status-info",
    ring: "border-status-info/30",
    dot: "bg-status-info",
  },
  aiTools: {
    chip: "bg-status-ai/12",
    icon: "text-status-ai",
    ring: "border-status-ai/30",
    dot: "bg-status-ai",
  },
  templates: {
    chip: "bg-status-info/12",
    icon: "text-status-info",
    ring: "border-status-info/30",
    dot: "bg-status-info",
  },
  community: {
    chip: "bg-status-success/12",
    icon: "text-status-success",
    ring: "border-status-success/30",
    dot: "bg-status-success",
  },
  progress: {
    chip: "bg-brand-primary/12",
    icon: "text-brand-primary",
    ring: "border-brand-primary/30",
    dot: "bg-brand-primary",
  },
  /* Settings is chrome, not content: deliberately neutral. */
  settings: {
    chip: "bg-bg-interactive",
    icon: "text-text-muted",
    ring: "border-border-strong",
    dot: "bg-text-muted",
  },
};
