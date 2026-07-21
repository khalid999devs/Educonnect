import { cn } from "@educonnect/ui";
import type { LucideIcon } from "lucide-react";

import {
  SECTION_ACCENT,
  type SectionAccent,
  type SectionAccentKey,
} from "@/components/shell/section-accent";

/**
 * Compact metric tile: icon, value, label, optional supporting caption.
 *
 * The value always carries `tabular-nums` (build brief 6.4). Elevation is a
 * border plus a surface fill, never a drop shadow.
 */
export type StatTileProps = {
  icon: LucideIcon;
  label: string;
  value: string;
  caption?: string;
  accent?: SectionAccentKey | SectionAccent;
  align?: "center" | "start";
  className?: string;
};

export function StatTile({
  icon: Icon,
  label,
  value,
  caption,
  accent = "home",
  align = "center",
  className,
}: StatTileProps) {
  const tokens = typeof accent === "string" ? SECTION_ACCENT[accent] : accent;

  return (
    <div
      className={cn(
        "rounded-md border border-border-subtle bg-bg-surface p-3 transition-colors hover:border-border-strong",
        align === "center" ? "text-center" : "text-left",
        className,
      )}
    >
      <Icon
        aria-hidden="true"
        className={cn(
          "size-4",
          tokens.icon,
          align === "center" ? "mx-auto" : null,
        )}
      />
      <p className="mt-1 text-h4 tabular-nums text-text-primary">{value}</p>
      <p className="text-caption text-text-muted">{label}</p>
      {caption ? (
        <p className="mt-0.5 text-caption text-text-secondary">{caption}</p>
      ) : null}
    </div>
  );
}
