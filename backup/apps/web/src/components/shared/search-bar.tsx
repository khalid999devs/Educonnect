"use client";

import { cn } from "@educonnect/ui";
import { Search } from "lucide-react";
import type { ReactNode } from "react";

/**
 * Reusable search input.
 *
 * `type="search"` gives the field an implicit `searchbox` role, so no explicit
 * `role` attribute is set. `label` is required and is applied as `aria-label`
 * because these bars sit in dense chrome with no room for a visible label.
 *
 * `tone="overlay"` is the variant used on top of a `PageCover` photo band: the
 * cover gradient fades to `/25` on the right, so the field carries its own
 * `bg-bg-surface/85 backdrop-blur-sm` chrome to hold >=4.5:1 contrast in both
 * themes. This is the project-wide choice; the cover gradient is never
 * darkened to compensate.
 */
export type SearchBarProps = {
  value: string;
  onChange: (value: string) => void;
  /** Required: becomes `aria-label` on the input. */
  label: string;
  placeholder?: string;
  /** Optional keyboard shortcut hint rendered inside the field. */
  shortcut?: ReactNode;
  tone?: "default" | "overlay";
  size?: "md" | "lg";
  onSubmit?: (value: string) => void;
  onFocus?: () => void;
  onBlur?: () => void;
  autoFocus?: boolean;
  name?: string;
  id?: string;
  className?: string;
};

const TONES = {
  default: "border-border-default bg-bg-canvas",
  overlay: "border-border-strong bg-bg-surface/85 backdrop-blur-sm",
} as const;

const SIZES = {
  md: "h-10",
  lg: "h-12",
} as const;

export function SearchBar({
  value,
  onChange,
  label,
  placeholder = "Search…",
  shortcut,
  tone = "default",
  size = "md",
  onSubmit,
  onFocus,
  onBlur,
  autoFocus = false,
  name,
  id,
  className,
}: SearchBarProps) {
  return (
    <div className={cn("relative w-full", className)}>
      <Search
        aria-hidden="true"
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
      />
      <input
        id={id}
        name={name}
        type="search"
        value={value}
        aria-label={label}
        placeholder={placeholder}
        autoFocus={autoFocus}
        onChange={(event) => onChange(event.target.value)}
        onFocus={onFocus}
        onBlur={onBlur}
        onKeyDown={(event) => {
          if (event.key === "Enter" && onSubmit) {
            event.preventDefault();
            onSubmit(value);
          }

          if (event.key === "Escape" && value.length > 0) {
            event.preventDefault();
            onChange("");
          }
        }}
        className={cn(
          "w-full rounded-md border pl-9 text-body text-text-primary placeholder:text-text-muted",
          "focus-visible:border-brand-focus focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand-focus",
          SIZES[size],
          TONES[tone],
          shortcut ? "pr-14" : "pr-3",
        )}
      />
      {shortcut ? (
        <kbd
          aria-hidden="true"
          className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded-sm border border-border-default bg-bg-subtle px-1.5 py-0.5 text-caption text-text-muted"
        >
          {shortcut}
        </kbd>
      ) : null}
    </div>
  );
}
