import { cn } from "@educonnect/ui";
import type { ReactNode } from "react";

/** Brand-blue section eyebrow used across the marketing pages. */
export function Eyebrow({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <p
      className={cn(
        "text-caption font-medium uppercase tracking-[0.18em] text-brand-primary",
        className,
      )}
    >
      {children}
    </p>
  );
}
