"use client";

import { X } from "lucide-react";
import {
  useEffect,
  useId,
  useRef,
  type KeyboardEvent,
  type ReactNode,
} from "react";

import { cn } from "../../lib/cn";
import { Button } from "./button";

const FOCUSABLE_SELECTOR = [
  "a[href]",
  "button:not([disabled])",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  '[tabindex]:not([tabindex="-1"])',
].join(", ");

export type DialogSize = "sm" | "md" | "lg";

const SIZE_CLASSES: Record<DialogSize, string> = {
  sm: "max-w-md",
  md: "max-w-lg",
  lg: "max-w-2xl",
};

export type DialogProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  size?: DialogSize;
  /** Extra classes for the panel, e.g. an app-level entrance animation. */
  contentClassName?: string;
  children: ReactNode;
  footer?: ReactNode;
};

/**
 * Accessible modal: labelled dialog semantics, focus trap with restore,
 * Escape and backdrop close, and a scroll-locked page behind it.
 */
export function Dialog({
  open,
  onClose,
  title,
  description,
  size = "md",
  contentClassName,
  children,
  footer,
}: DialogProps) {
  const titleId = useId();
  const descriptionId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const restoreRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    restoreRef.current =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    const panel = panelRef.current;
    const first = panel?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR);
    (first ?? panel)?.focus();

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
      restoreRef.current?.focus();
    };
  }, [open]);

  if (!open) {
    return null;
  }

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === "Escape") {
      event.stopPropagation();
      onClose();
      return;
    }

    if (event.key !== "Tab") {
      return;
    }

    const panel = panelRef.current;

    if (!panel) {
      return;
    }

    const focusable = Array.from(
      panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
    );

    if (focusable.length === 0) {
      event.preventDefault();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) {
      return;
    }

    const active = document.activeElement;

    if (event.shiftKey && (active === first || active === panel)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
      onKeyDown={onKeyDown}
    >
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-black/60"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
        className={cn(
          "relative flex max-h-[calc(100dvh-2rem)] w-full flex-col rounded-xl border border-border-default bg-bg-surface shadow-lg outline-none",
          SIZE_CLASSES[size],
          contentClassName,
        )}
      >
        <div className="flex items-start justify-between gap-4 border-b border-border-default px-5 py-4">
          <div className="space-y-1">
            <h2 id={titleId} className="text-h4 text-text-primary">
              {title}
            </h2>
            {description ? (
              <p id={descriptionId} className="text-body text-text-muted">
                {description}
              </p>
            ) : null}
          </div>
          <Button
            variant="ghost"
            size="sm"
            aria-label="Close dialog"
            onClick={onClose}
            className="shrink-0"
          >
            <X aria-hidden="true" className="size-4" />
          </Button>
        </div>

        <div className="overflow-y-auto px-5 py-4">{children}</div>

        {footer ? (
          <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border-default px-5 py-4">
            {footer}
          </div>
        ) : null}
      </div>
    </div>
  );
}
