import { cn } from "@educonnect/ui";
import type { LucideIcon } from "lucide-react";

import {
  SECTION_ACCENT,
  type SectionAccent,
  type SectionAccentKey,
} from "@/components/shell/section-accent";

/**
 * Canonical tinted icon chip (build brief 6.3).
 *
 * Sizing precedent: `rounded-xl` for hero chips, `rounded-md` for inline
 * chips, `rounded-full` for milestone / empty-state avatars. Chip fills stay
 * at `/12` - the alpha chosen because it reads correctly against both
 * `#ffffff` and `#0b1020`.
 */
export type IconChipSize = "sm" | "md" | "lg" | "hero";

const SIZES: Record<IconChipSize, { box: string; glyph: string }> = {
  sm: { box: "size-8 rounded-md", glyph: "size-4" },
  md: { box: "size-9 rounded-md", glyph: "size-5" },
  lg: { box: "size-11 rounded-xl", glyph: "size-5" },
  hero: { box: "size-13 rounded-xl", glyph: "size-6" },
};

export type IconChipProps = {
  icon: LucideIcon;
  accent: SectionAccentKey | SectionAccent;
  size?: IconChipSize;
  /** Renders the accent border alongside the fill. */
  bordered?: boolean;
  /** Milestone and empty-state avatars use a circular chip. */
  round?: boolean;
  className?: string;
};

function resolveAccent(accent: SectionAccentKey | SectionAccent) {
  return typeof accent === "string" ? SECTION_ACCENT[accent] : accent;
}

export function IconChip({
  icon: Icon,
  accent,
  size = "md",
  bordered = false,
  round = false,
  className,
}: IconChipProps) {
  const tokens = resolveAccent(accent);
  const dimensions = SIZES[size];

  return (
    <span
      data-testid="icon-chip"
      className={cn(
        "flex shrink-0 items-center justify-center",
        dimensions.box,
        round ? "rounded-full" : null,
        tokens.chip,
        bordered ? cn("border", tokens.ring) : null,
        className,
      )}
    >
      <Icon aria-hidden="true" className={cn(dimensions.glyph, tokens.icon)} />
    </span>
  );
}
