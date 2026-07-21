"use client";

import { cn } from "@educonnect/ui";
import { Bookmark, BookmarkCheck } from "lucide-react";

import {
  SECTION_ACCENT,
  type SectionAccentKey,
} from "@/components/shell/section-accent";

/**
 * The Saved chip: one per section, always driven by the SERVER-side
 * `preference=saved` filter (see `catalog-preference.ts`), never by filtering
 * a loaded page client-side.
 *
 * It is a toggle, so it carries `aria-pressed` rather than pretending to be a
 * tab. In the default `accent` tone, the active state is the section accent as
 * chip fill plus accent border - decoration only, at the fixed `/12` and `/30`
 * alphas.
 *
 * The `bookmark` tone drops the section hue for a fixed amber, the universal
 * "saved" colour. It exists so the chip can sit beside a row of section-tinted
 * filters (the Second Brain purpose chips) and still read as a distinct Saved
 * affordance rather than one more filter in the set. The amber shows even when
 * inactive, so the chip's identity does not depend on its state.
 */
export type SavedFilterChipProps = {
  active: boolean;
  onToggle: () => void;
  /** Section hue for the default tone. Ignored when `tone` is `bookmark`. */
  accent?: SectionAccentKey;
  /** `bookmark` gives a fixed amber treatment instead of the section accent. */
  tone?: "accent" | "bookmark";
  /** What is being filtered, e.g. "tools, prompts and workflows". */
  describes: string;
  className?: string;
};

export function SavedFilterChip({
  active,
  onToggle,
  accent,
  tone = "accent",
  describes,
  className,
}: SavedFilterChipProps) {
  const tokens = accent ? SECTION_ACCENT[accent] : SECTION_ACCENT.home;
  const Icon = active ? BookmarkCheck : Bookmark;

  const bookmark = tone === "bookmark";

  const activeClasses = bookmark
    ? "border-status-deadline/55 bg-status-deadline/15 text-text-primary"
    : cn(tokens.ring, tokens.chip, "text-text-primary");
  const inactiveClasses = bookmark
    ? "border-status-deadline/40 bg-bg-surface text-text-secondary hover:border-status-deadline/70 hover:bg-status-deadline/10"
    : "border-border-default bg-bg-surface text-text-secondary hover:border-border-strong hover:text-text-primary";
  const iconColor = bookmark
    ? "text-status-deadline"
    : active
      ? tokens.icon
      : "text-text-muted";

  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={onToggle}
      className={cn(
        "flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-body transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
        active ? activeClasses : inactiveClasses,
        className,
      )}
    >
      <Icon aria-hidden="true" className={cn("size-4", iconColor)} />
      Saved
      <span className="sr-only">
        {active
          ? ` - showing only saved ${describes}`
          : ` - show only saved ${describes}`}
      </span>
    </button>
  );
}
