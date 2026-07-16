"use client";

import { Sparkles, X } from "lucide-react";

import { cn } from "../../lib/cn";

export type CopilotTriggerProps = {
  isOpen?: boolean;
  onToggle?: () => void;
  label?: string;
  className?: string;
};

/**
 * The single floating Copilot trigger: 56px, lower-right safe area,
 * collapsed by default (doc 04). Each app shell renders exactly one and
 * owns the open state; feature pages never mount their own.
 */
export function CopilotTrigger({
  isOpen = false,
  onToggle,
  label = "EduConnect Copilot",
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
        "flex size-14 items-center justify-center rounded-full bg-brand-primary text-white shadow-glow",
        "transition-colors hover:bg-brand-primary-hover",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
        className,
      )}
    >
      {isOpen ? (
        <X aria-hidden="true" className="size-6" />
      ) : (
        <Sparkles aria-hidden="true" className="size-6" />
      )}
    </button>
  );
}
