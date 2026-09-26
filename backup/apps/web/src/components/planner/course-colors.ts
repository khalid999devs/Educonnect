import { courseColorSlot } from "./time";

/**
 * Course color coding uses the six documented hue tokens only (doc 04 bans
 * raw color classes). Class strings are static so Tailwind can see them.
 */
export type CourseColor = {
  block: string;
  edge: string;
  dot: string;
  text: string;
};

const PALETTE: CourseColor[] = [
  {
    block: "bg-brand-primary/15",
    edge: "border-brand-primary",
    dot: "bg-brand-primary",
    text: "text-brand-primary",
  },
  {
    block: "bg-status-ai/15",
    edge: "border-status-ai",
    dot: "bg-status-ai",
    text: "text-status-ai",
  },
  {
    block: "bg-status-research/15",
    edge: "border-status-research",
    dot: "bg-status-research",
    text: "text-status-research",
  },
  {
    block: "bg-status-success/15",
    edge: "border-status-success",
    dot: "bg-status-success",
    text: "text-status-success",
  },
  {
    block: "bg-status-deadline/15",
    edge: "border-status-deadline",
    dot: "bg-status-deadline",
    text: "text-status-deadline",
  },
  {
    block: "bg-status-info/15",
    edge: "border-status-info",
    dot: "bg-status-info",
    text: "text-status-info",
  },
];

/** Neutral styling for items without a course. */
export const NO_COURSE_COLOR: CourseColor = {
  block: "bg-bg-interactive",
  edge: "border-border-strong",
  dot: "bg-text-muted",
  text: "text-text-secondary",
};

export function courseColor(courseId: string | null | undefined): CourseColor {
  if (!courseId) {
    return NO_COURSE_COLOR;
  }

  return PALETTE[courseColorSlot(courseId, PALETTE.length)] ?? NO_COURSE_COLOR;
}
