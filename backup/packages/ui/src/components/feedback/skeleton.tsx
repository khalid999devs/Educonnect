import type { ComponentProps } from "react";

import { cn } from "../../lib/cn";

/** Loading placeholder block; static under reduced motion. */
export function Skeleton({ className, ...divProps }: ComponentProps<"div">) {
  return (
    <div
      {...divProps}
      aria-hidden="true"
      className={cn(
        "animate-pulse rounded-md bg-bg-interactive motion-reduce:animate-none",
        className,
      )}
    />
  );
}
