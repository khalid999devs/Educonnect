import { CircleAlert } from "lucide-react";
import type { ReactNode } from "react";

import { cn } from "../../lib/cn";
import { Button } from "../primitives/button";

export type ErrorStateProps = {
  title?: string;
  description?: string;
  onRetry?: () => void;
  retryLabel?: string;
  action?: ReactNode;
  className?: string;
};

export function ErrorState({
  title = "Something went wrong",
  description = "The request could not be completed. Try again.",
  onRetry,
  retryLabel = "Try again",
  action,
  className,
}: ErrorStateProps) {
  return (
    <div
      role="alert"
      className={cn(
        "flex flex-col items-center justify-center gap-3 rounded-lg border border-status-error/30 bg-status-error/5 px-6 py-12 text-center",
        className,
      )}
    >
      <span className="flex size-12 items-center justify-center rounded-full bg-status-error/10">
        <CircleAlert aria-hidden="true" className="size-6 text-status-error" />
      </span>
      <p className="text-h4 text-text-primary">{title}</p>
      <p className="max-w-md text-body text-text-secondary">{description}</p>
      {onRetry || action ? (
        <div className="mt-2 flex items-center gap-3">
          {onRetry ? (
            <Button variant="secondary" onClick={onRetry}>
              {retryLabel}
            </Button>
          ) : null}
          {action}
        </div>
      ) : null}
    </div>
  );
}
