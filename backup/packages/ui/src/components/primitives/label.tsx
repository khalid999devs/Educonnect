import type { ComponentProps } from "react";

import { cn } from "../../lib/cn";

export type LabelProps = ComponentProps<"label"> & {
  required?: boolean;
};

export function Label({
  required = false,
  className,
  children,
  ...labelProps
}: LabelProps) {
  return (
    <label
      {...labelProps}
      className={cn("block text-label text-text-secondary", className)}
    >
      {children}
      {required ? (
        <span aria-hidden="true" className="ml-0.5 text-status-error">
          *
        </span>
      ) : null}
    </label>
  );
}
