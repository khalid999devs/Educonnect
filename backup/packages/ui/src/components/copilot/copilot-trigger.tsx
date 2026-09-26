"use client";

import { Sparkles, X } from "lucide-react";

import { cn } from "../../lib/cn";

export type CopilotTriggerProps = {
  isOpen?: boolean;
  onToggle?: () => void;
  label?: string;
  /** "pill" shows the labeled lozenge from the reference art. */
  variant?: "icon" | "pill";
  className?: string;
};

/**
 * The single floating Copilot trigger: lower-right safe area, collapsed by
 * default (doc 04). Each app shell renders exactly one and owns the open
 * state; feature pages never mount their own.
 */
export function CopilotTrigger({
  isOpen = false,
  onToggle,
  label = "EduConnect Copilot",
  variant = "icon",
  className,
}: CopilotTriggerProps) {
  return (
    <button
      type="button"
      aria-label={isOpen ? `Close ${label}` : `Open ${label}`}
      aria-expanded={isOpen}
      aria-haspopup="dialog"
      onClick={onToggle}
      className={cn(
        "fixed bottom-[max(1.5rem,env(safe-area-inset-bottom))] right-6 z-50",
        "flex items-center justify-center rounded-full bg-brand-primary text-white shadow-glow",
        variant === "pill" ? "h-12 gap-2 px-5 text-button" : "size-14",
        "transition-colors hover:bg-brand-primary-hover",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
        className,
      )}
    >
      {isOpen ? (
        <X
          aria-hidden="true"
          className={variant === "pill" ? "size-5" : "size-6"}
        />
      ) : (
        <Sparkles
          aria-hidden="true"
          className={variant === "pill" ? "size-5" : "size-6"}
        />
      )}
      {variant === "pill" ? <span aria-hidden="true">Copilot</span> : null}
    </button>
  );
}
