import type { ReactNode } from "react";

import { cn } from "../../lib/cn";

export type TopBarProps = {
  children?: ReactNode;
  leading?: ReactNode;
  trailing?: ReactNode;
  /** "compact" is for the density-first admin console. */
  density?: "default" | "compact";
  className?: string;
};

/** Product top bar: 72px default height (doc 04). */
export function TopBar({
  children,
  leading,
  trailing,
  density = "default",
  className,
}: TopBarProps) {
  return (
    <header
      className={cn(
        "sticky top-0 z-30 flex items-center gap-4 border-b border-border-default bg-bg-surface px-6",
        density === "default" ? "h-18" : "h-14",
        className,
      )}
    >
      {leading}
      <div className="flex min-w-0 flex-1 items-center gap-4">{children}</div>
      {trailing ? (
        <div className="flex shrink-0 items-center gap-3">{trailing}</div>
      ) : null}
    </header>
  );
}
