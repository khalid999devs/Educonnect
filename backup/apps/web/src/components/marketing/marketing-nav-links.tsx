"use client";

import { cn } from "@educonnect/ui";
import Link from "next/link";
import { usePathname } from "next/navigation";

const LINKS = [
  { href: "/about", label: "About" },
  { href: "/blog", label: "Blog" },
  { href: "/contact", label: "Contact" },
] as const;

export function MarketingNavLinks() {
  const pathname = usePathname();

  return (
    <ul className="flex flex-wrap items-center gap-1">
      {LINKS.map((link) => {
        const isActive =
          pathname === link.href || pathname.startsWith(`${link.href}/`);

        return (
          <li key={link.href}>
            <Link
              href={link.href}
              aria-current={isActive ? "page" : undefined}
              className={cn(
                "inline-flex h-10 items-center rounded-md px-3 text-body font-medium transition-colors",
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                isActive
                  ? "bg-bg-interactive text-brand-primary"
                  : "text-text-secondary hover:bg-bg-interactive hover:text-text-primary",
              )}
            >
              {link.label}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
