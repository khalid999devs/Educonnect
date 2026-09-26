"use client";

import { cn } from "@educonnect/ui";
import { Bookmark, BookmarkCheck } from "lucide-react";

import { SECTION_ACCENT } from "@/components/shell/section-accent";

/**
 * The per-item bookmark toggle for the Second Brain.
 *
 * A two-state toggle, so it carries `aria-pressed` and an `aria-label` that
 * reads "Saved" or "Save" rather than pretending to be a link. The saved state
 * uses the section hue (`status-research`) as chip fill plus accent border,
 * decoration only, at the fixed `/12` and `/30` alphas.
 *
 * It preventDefault/stopPropagation so it can sit on top of a card-wide link
 * without the click also navigating.
 */
export type SaveToggleProps = {
  saved: boolean;
  busy?: boolean;
  onToggle: () => void;
  className?: string;
};

export function SaveToggle({
  saved,
  busy = false,
  onToggle,
  className,
}: SaveToggleProps) {
  const tokens = SECTION_ACCENT.secondBrain;
  const Icon = saved ? BookmarkCheck : Bookmark;

  return (
    <button
      type="button"
      aria-pressed={saved}
      aria-label={saved ? "Saved" : "Save"}
      disabled={busy}
      onClick={(event) => {
        event.preventDefault();
        event.stopPropagation();
        onToggle();
      }}
      className={cn(
        "flex size-8 shrink-0 items-center justify-center rounded-md border transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
        "disabled:cursor-not-allowed disabled:opacity-60",
        saved
          ? cn(tokens.ring, tokens.chip, tokens.icon)
          : "border-border-subtle text-text-muted hover:border-border-strong hover:text-text-secondary",
        className,
      )}
    >
      <Icon aria-hidden="true" className="size-4" />
    </button>
  );
}
