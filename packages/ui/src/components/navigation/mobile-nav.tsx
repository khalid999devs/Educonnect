import type { LucideIcon } from "lucide-react";
import Link from "next/link";
import type { ReactNode } from "react";

import { cn } from "../../lib/cn";

/** Core bottom navigation for <768px viewports (doc 04). */
export function MobileNav({
  children,
  label,
  className,
}: {
  children: ReactNode;
  label: string;
  className?: string;
}) {
  return (
    <nav
      aria-label={label}
      className={cn(
        "fixed inset-x-0 bottom-0 z-40 border-t border-border-default bg-bg-surface pb-[env(safe-area-inset-bottom)] md:hidden",
        className,
      )}
    >
      <ul className="flex">{children}</ul>
    </nav>
  );
}

export type MobileNavItemProps = {
  label: string;
  icon: LucideIcon;
  href?: string;
  isActive?: boolean;
  disabled?: boolean;
};

export function MobileNavItem({
  label,
  icon: Icon,
  href,
  isActive = false,
  disabled = false,
}: MobileNavItemProps) {
  const baseClasses =
    "flex min-h-14 w-full flex-col items-center justify-center gap-1 py-2 text-caption";

  if (disabled || !href) {
    return (
      <li className="flex-1">
        <span
          aria-disabled="true"
          className={cn(baseClasses, "text-text-muted")}
        >
          <Icon aria-hidden="true" className="size-5" />
          {label}
        </span>
      </li>
    );
  }

  return (
    <li className="flex-1">
      <Link
        href={href}
        aria-current={isActive ? "page" : undefined}
        className={cn(
          baseClasses,
          "focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-focus",
          isActive
            ? "font-medium text-brand-primary"
            : "text-text-secondary hover:text-text-primary",
        )}
      >
        <Icon aria-hidden="true" className="size-5" />
        {label}
      </Link>
    </li>
  );
}
