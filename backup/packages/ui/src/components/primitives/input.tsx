import type { ComponentProps } from "react";

import { cn } from "../../lib/cn";

const CONTROL_CLASSES = [
  "w-full rounded-md border border-border-default bg-bg-surface text-body text-text-primary",
  "placeholder:text-text-muted",
  "focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand-focus focus-visible:border-brand-focus",
  "aria-invalid:border-status-error aria-invalid:focus-visible:outline-status-error",
  "disabled:cursor-not-allowed disabled:bg-bg-subtle disabled:text-text-muted",
].join(" ");

export type InputProps = ComponentProps<"input">;

export function Input({ className, ...inputProps }: InputProps) {
  return (
    <input
      {...inputProps}
      className={cn(CONTROL_CLASSES, "h-11 px-3.5", className)}
    />
  );
}

export type TextareaProps = ComponentProps<"textarea">;

export function Textarea({ className, ...textareaProps }: TextareaProps) {
  return (
    <textarea
      {...textareaProps}
      className={cn(CONTROL_CLASSES, "min-h-28 px-3.5 py-3", className)}
    />
  );
}
