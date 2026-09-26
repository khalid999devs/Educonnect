import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

import { cn } from "../../lib/cn";

export type EmptyStateProps = {
  title: string;
  description?: string;
  icon?: LucideIcon;
  action?: ReactNode;
  className?: string;
};

/**
 * Honest empty state: states what is genuinely absent and, when useful, the
 * one action that changes it. Never used to fake activity or metrics.
 */
export function EmptyState({
  title,
  description,
  icon: Icon,
  action,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center gap-3 rounded-lg border border-border-default bg-bg-surface px-6 py-12 text-center",
        className,
      )}
    >
      {Icon ? (
        <span className="flex size-12 items-center justify-center rounded-full bg-bg-interactive">
          <Icon aria-hidden="true" className="size-6 text-text-muted" />
        </span>
      ) : null}
      <p className="text-h4 text-text-primary">{title}</p>
      {description ? (
        <p className="max-w-md text-body text-text-secondary">{description}</p>
      ) : null}
      {action ? <div className="mt-2">{action}</div> : null}
    </div>
  );
}
