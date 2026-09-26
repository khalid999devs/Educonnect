import { clsx, type ClassValue } from "clsx";
import { extendTailwindMerge } from "tailwind-merge";

const TYPE_SCALE = [
  "display-xl",
  "display",
  "h1",
  "h2",
  "h3",
  "h4",
  "body-lg",
  "body",
  "label",
  "caption",
  "button",
];

const COLOR_TOKENS = [
  "brand-primary",
  "brand-primary-hover",
  "brand-focus",
  "bg-canvas",
  "bg-surface",
  "bg-subtle",
  "bg-elevated",
  "bg-interactive",
  "text-primary",
  "text-secondary",
  "text-muted",
  "border-default",
  "border-subtle",
  "border-strong",
  "status-success",
  "status-warning",
  "status-deadline",
  "status-error",
  "status-info",
  "status-ai",
  "status-research",
  "white",
  "black",
  "transparent",
  "current",
];

/**
 * tailwind-merge cannot see the custom theme, so the semantic type scale and
 * color tokens are registered explicitly; otherwise `text-h4` vs
 * `text-text-primary` would be treated as conflicting utilities.
 */
const twMerge = extendTailwindMerge({
  override: {
    classGroups: {
      "font-size": [{ text: TYPE_SCALE }],
    },
  },
  extend: {
    classGroups: {
      "text-color": [{ text: COLOR_TOKENS }],
    },
  },
});

export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
