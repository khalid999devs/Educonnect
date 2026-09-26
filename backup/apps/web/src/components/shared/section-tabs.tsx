"use client";

import { cn } from "@educonnect/ui";
import type { LucideIcon } from "lucide-react";
import { useCallback, useRef } from "react";

/**
 * Top tab bar for section hubs (Community: Posts | Mentors | Groups | People).
 *
 * URL-syncable by design: it is fully controlled through `value` + `onChange`,
 * so the owning view can mirror the active tab into a search param without the
 * component knowing anything about routing.
 *
 * Keyboard model follows the WAI-ARIA tabs pattern with manual activation:
 * roving `tabIndex`, ArrowLeft / ArrowRight wrap around, Home / End jump to
 * the ends, and moving focus also selects - which is what makes a URL-synced
 * tab bar feel native.
 */
export type SectionTab<TValue extends string = string> = {
  value: TValue;
  label: string;
  icon?: LucideIcon;
  /** Rendered as a trailing count pill. */
  count?: number;
};

export type SectionTabsProps<TValue extends string = string> = {
  tabs: readonly SectionTab<TValue>[];
  value: TValue;
  onChange: (value: TValue) => void;
  /** Required: names the tab list for assistive technology. */
  label: string;
  /** Ties each tab to its panel element id. */
  panelId?: (value: TValue) => string;
  className?: string;
};

export function SectionTabs<TValue extends string = string>({
  tabs,
  value,
  onChange,
  label,
  panelId,
  className,
}: SectionTabsProps<TValue>) {
  const refs = useRef(new Map<TValue, HTMLButtonElement>());

  const focusAndSelect = useCallback(
    (next: SectionTab<TValue> | undefined) => {
      if (!next) {
        return;
      }

      refs.current.get(next.value)?.focus();
      onChange(next.value);
    },
    [onChange],
  );

  const onKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    const index = tabs.findIndex((tab) => tab.value === value);

    if (index < 0) {
      return;
    }

    switch (event.key) {
      case "ArrowRight":
      case "ArrowDown":
        event.preventDefault();
        focusAndSelect(tabs[(index + 1) % tabs.length]);
        break;
      case "ArrowLeft":
      case "ArrowUp":
        event.preventDefault();
        focusAndSelect(tabs[(index - 1 + tabs.length) % tabs.length]);
        break;
      case "Home":
        event.preventDefault();
        focusAndSelect(tabs[0]);
        break;
      case "End":
        event.preventDefault();
        focusAndSelect(tabs[tabs.length - 1]);
        break;
      default:
        break;
    }
  };

  return (
    <div
      role="tablist"
      aria-label={label}
      aria-orientation="horizontal"
      className={cn(
        "flex items-stretch gap-1 overflow-x-auto border-b border-border-default",
        className,
      )}
    >
      {tabs.map((tab) => {
        const selected = tab.value === value;
        const Icon = tab.icon;

        return (
          <button
            key={tab.value}
            type="button"
            role="tab"
            id={`section-tab-${tab.value}`}
            aria-selected={selected}
            aria-controls={panelId ? panelId(tab.value) : undefined}
            tabIndex={selected ? 0 : -1}
            ref={(node) => {
              if (node) {
                refs.current.set(tab.value, node);
              } else {
                refs.current.delete(tab.value);
              }
            }}
            onClick={() => onChange(tab.value)}
            onKeyDown={onKeyDown}
            className={cn(
              "-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3.5 py-2.5 text-button transition-colors",
              "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
              selected
                ? "border-brand-primary text-brand-primary"
                : "border-transparent text-text-muted hover:border-border-strong hover:text-text-primary",
            )}
          >
            {Icon ? <Icon aria-hidden="true" className="size-4" /> : null}
            {tab.label}
            {typeof tab.count === "number" ? (
              <span
                className={cn(
                  "rounded-full px-1.5 py-0.5 text-caption tabular-nums",
                  selected
                    ? "bg-brand-primary/12 text-brand-primary"
                    : "bg-bg-subtle text-text-muted",
                )}
              >
                {tab.count}
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
