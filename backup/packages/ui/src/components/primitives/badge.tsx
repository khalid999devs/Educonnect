import type { ComponentProps } from "react";

import { cn } from "../../lib/cn";

export type BadgeVariant =
  | "neutral"
  | "brand"
  | "success"
  | "warning"
  | "deadline"
  | "error"
  | "info"
  | "ai"
  | "research";

const VARIANTS: Record<BadgeVariant, string> = {
  neutral: "border-border-default bg-bg-subtle text-text-secondary",
  brand: "border-brand-primary/30 bg-bg-interactive text-brand-primary",
  success: "border-status-success/30 bg-status-success/10 text-status-success",
  warning: "border-status-warning/40 bg-status-warning/10 text-text-primary",
  deadline:
    "border-status-deadline/40 bg-status-deadline/10 text-status-deadline",
  error: "border-status-error/30 bg-status-error/10 text-status-error",
  info: "border-status-info/30 bg-status-info/10 text-status-info",
  ai: "border-status-ai/30 bg-status-ai/10 text-status-ai",
  research:
    "border-status-research/30 bg-status-research/10 text-status-research",
};

export type BadgeProps = ComponentProps<"span"> & {
  variant?: BadgeVariant;
};

/**
 * Status chip. The text label carries the meaning; color is reinforcement
 * only (doc 04 non-color status rule).
 */
export function Badge({
  variant = "neutral",
  className,
  ...spanProps
}: BadgeProps) {
  return (
    <span
      {...spanProps}
      className={cn(
        "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-caption font-medium",
        VARIANTS[variant],
        className,
      )}
    />
  );
}
