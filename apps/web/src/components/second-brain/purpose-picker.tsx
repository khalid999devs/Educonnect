"use client";

import { Badge, cn } from "@educonnect/ui";
import { Check } from "lucide-react";

import { SECTION_ACCENT } from "@/components/shell/section-accent";
import type { KnowledgePurpose } from "@/lib/api/second-brain";
import { PURPOSES } from "./purpose";

export type PurposePickerProps = {
  value: KnowledgePurpose | null;
  onChange: (purpose: KnowledgePurpose) => void;
  /** The purpose the backend pre-selected, if any. Shown as an advisory badge
   * so the student can see what was guessed and that it is only a guess. */
  suggested?: KnowledgePurpose | null;
  disabled?: boolean;
  /** Names the radio group for assistive technology. */
  label: string;
  className?: string;
};

/**
 * Step two: ask the purpose.
 *
 * The backend pre-selects the most likely option, which is ADVISORY and never
 * authoritative. All four options are always visible and every one is a single
 * click, so a wrong pre-selection is a one-click correction rather than an
 * edit-mode round trip.
 *
 * Implemented as a real radiogroup: arrow keys move between options and the
 * selected option is the only tab stop, which is the native radio model.
 */
export function PurposePicker({
  value,
  onChange,
  suggested = null,
  disabled = false,
  label,
  className,
}: PurposePickerProps) {
  const activeIndex = Math.max(
    0,
    PURPOSES.findIndex((purpose) => purpose.value === value),
  );

  const onKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    const step =
      event.key === "ArrowRight" || event.key === "ArrowDown"
        ? 1
        : event.key === "ArrowLeft" || event.key === "ArrowUp"
          ? -1
          : 0;

    if (step === 0 || disabled) {
      return;
    }

    event.preventDefault();
    const next =
      PURPOSES[(activeIndex + step + PURPOSES.length) % PURPOSES.length];

    if (next) {
      onChange(next.value);
    }
  };

  return (
    <div
      role="radiogroup"
      aria-label={label}
      className={cn("grid gap-2 sm:grid-cols-2", className)}
    >
      {PURPOSES.map((purpose, index) => {
        const selected = purpose.value === value;
        const accent = SECTION_ACCENT[purpose.accent];
        const Icon = purpose.icon;

        return (
          <button
            key={purpose.value}
            type="button"
            role="radio"
            aria-checked={selected}
            tabIndex={index === activeIndex ? 0 : -1}
            disabled={disabled}
            onClick={() => onChange(purpose.value)}
            onKeyDown={onKeyDown}
            className={cn(
              "flex items-start gap-3 rounded-lg border p-3 text-left transition-colors",
              "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
              "disabled:cursor-not-allowed disabled:opacity-60",
              selected
                ? cn("bg-bg-elevated shadow-glow-sm", accent.ring)
                : "border-border-subtle bg-bg-surface hover:border-border-strong",
            )}
          >
            <span
              className={cn(
                "flex size-9 shrink-0 items-center justify-center rounded-md",
                accent.chip,
              )}
            >
              <Icon aria-hidden="true" className={cn("size-5", accent.icon)} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="flex flex-wrap items-center gap-1.5">
                <span className="text-body font-medium text-text-primary">
                  {purpose.label}
                </span>
                {suggested === purpose.value ? (
                  <Badge variant="ai">Suggested</Badge>
                ) : null}
              </span>
              <span className="mt-0.5 block text-caption text-text-secondary">
                {purpose.description}
              </span>
            </span>
            {selected ? (
              <Check
                aria-hidden="true"
                className={cn("size-4 shrink-0", accent.icon)}
              />
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
