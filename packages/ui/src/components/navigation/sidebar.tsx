import type { LucideIcon } from "lucide-react";
import Link from "next/link";
import type { ReactNode } from "react";

import { cn } from "../../lib/cn";
import { Badge } from "../primitives/badge";

/** App sidebar rail: 240px within the approved 232-248px range (doc 04). */
export function Sidebar({
  children,
  header,
  footer,
  label,
  className,
}: {
  children: ReactNode;
  header?: ReactNode;
  footer?: ReactNode;
  label: string;
  className?: string;
}) {
  return (
    <aside
      className={cn(
        "flex h-full w-60 flex-col border-r border-border-default bg-bg-surface",
        className,
      )}
    >
      {header ? (
        <div className="flex h-18 shrink-0 items-center border-b border-border-subtle px-5">
          {header}
        </div>
      ) : null}
      <nav
        aria-label={label}
        className="flex-1 space-y-6 overflow-y-auto px-3 py-5"
      >
        {children}
      </nav>
      {footer ? (
        <div className="shrink-0 border-t border-border-subtle p-4">
          {footer}
        </div>
      ) : null}
    </aside>
  );
}

export function SidebarSection({
  title,
  children,
}: {
  title?: string;
  children: ReactNode;
}) {
  return (
    <div>
      {title ? (
        <p className="mb-1.5 px-3 text-caption font-medium text-text-muted">
          {title}
        </p>
      ) : null}
      <ul className="space-y-0.5">{children}</ul>
    </div>
  );
}

export type SidebarItemProps = {
  label: string;
  icon: LucideIcon;
  href?: string;
  isActive?: boolean;
  /**
   * Planned destinations render as non-links with a "Soon" marker so unbuilt
   * features are never presented as available (doc 07).
   */
  disabled?: boolean;
  disabledLabel?: string;
};

export function SidebarItem({
  label,
  icon: Icon,
  href,
  isActive = false,
  disabled = false,
  disabledLabel = "Soon",
}: SidebarItemProps) {
  const baseClasses =
    "flex h-11 items-center gap-3 rounded-md px-3 text-body font-medium";

  if (disabled || !href) {
    return (
      <li>
        <span
          aria-disabled="true"
          className={cn(baseClasses, "cursor-not-allowed text-text-muted")}
        >
          <Icon aria-hidden="true" className="size-4.5 shrink-0" />
          <span className="flex-1 truncate">{label}</span>
          <Badge variant="neutral">{disabledLabel}</Badge>
        </span>
      </li>
    );
  }

  return (
    <li>
      <Link
        href={href}
        aria-current={isActive ? "page" : undefined}
        className={cn(
          baseClasses,
          "transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
          isActive
            ? "bg-brand-primary text-white shadow-glow-sm"
            : "text-text-secondary hover:bg-bg-interactive hover:text-text-primary",
        )}
      >
        <Icon aria-hidden="true" className="size-4.5 shrink-0" />
        <span className="flex-1 truncate">{label}</span>
      </Link>
    </li>
  );
}
