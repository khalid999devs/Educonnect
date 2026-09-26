import { ChevronDown } from "lucide-react";
import type { ComponentProps } from "react";

import { cn } from "../../lib/cn";

export type SelectProps = ComponentProps<"select">;

/** Styled native select; native semantics keep it fully accessible. */
export function Select({ className, children, ...selectProps }: SelectProps) {
  return (
    <span className={cn("relative block", className)}>
      <select
        {...selectProps}
        className={cn(
          "h-11 w-full appearance-none rounded-md border border-border-default bg-bg-surface pl-3.5 pr-10 text-body text-text-primary",
          "focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand-focus focus-visible:border-brand-focus",
          "aria-invalid:border-status-error",
          "disabled:cursor-not-allowed disabled:bg-bg-subtle disabled:text-text-muted",
        )}
      >
        {children}
      </select>
      <ChevronDown
        aria-hidden="true"
        className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
      />
    </span>
  );
}
