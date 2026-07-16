import {
  buttonClasses,
  EduConnectThemedLogo,
  ThemeToggle,
} from "@educonnect/ui";
import Link from "next/link";
import type { ReactNode } from "react";

import { MarketingNavLinks } from "@/components/marketing/marketing-nav-links";

export default function MarketingLayout({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-dvh bg-bg-canvas">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-bg-surface focus:px-4 focus:py-2 focus:text-body focus:text-text-primary"
      >
        Skip to main content
      </a>

      <header className="sticky top-0 z-40 border-b border-border-subtle bg-bg-canvas/85 backdrop-blur-md">
        <div className="mx-auto flex min-h-18 w-full max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-6 py-3">
          <Link
            href="/"
            aria-label="EduConnect home"
            className="inline-flex items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
          >
            <EduConnectThemedLogo width={168} decorative />
          </Link>
          <nav
            aria-label="Marketing"
            className="order-3 w-full sm:order-none sm:w-auto sm:flex-1"
          >
            <MarketingNavLinks />
          </nav>
          <div className="ml-auto flex items-center gap-3 sm:ml-0">
            <ThemeToggle />
            <Link
              href="/demo"
              className={buttonClasses(
                { variant: "primary", size: "md" },
                "hidden sm:inline-flex",
              )}
            >
              Try the demo
            </Link>
          </div>
        </div>
      </header>

      <main id="main">{children}</main>

      <footer className="border-t border-border-subtle bg-bg-surface">
        <div className="mx-auto grid w-full max-w-6xl gap-10 px-6 py-12 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-3 sm:col-span-2">
            <EduConnectThemedLogo width={140} />
            <p className="max-w-sm text-body text-text-secondary">
              The academic workspace that guides university students from need
              to action — built on real records, never fabricated numbers.
            </p>
          </div>
          <nav aria-label="Product" className="space-y-2">
            <p className="text-label text-text-primary">Product</p>
            <ul className="space-y-1.5 text-body">
              <li>
                <Link
                  href="/demo"
                  className="text-text-secondary hover:text-text-primary"
                >
                  Live Demo
                </Link>
              </li>
              <li>
                <Link
                  href="/design-system"
                  className="text-text-secondary hover:text-text-primary"
                >
                  Design system
                </Link>
              </li>
            </ul>
          </nav>
          <nav aria-label="Company" className="space-y-2">
            <p className="text-label text-text-primary">Company</p>
            <ul className="space-y-1.5 text-body">
              <li>
                <Link
                  href="/about"
                  className="text-text-secondary hover:text-text-primary"
                >
                  About
                </Link>
              </li>
              <li>
                <Link
                  href="/blog"
                  className="text-text-secondary hover:text-text-primary"
                >
                  Blog
                </Link>
              </li>
              <li>
                <Link
                  href="/contact"
                  className="text-text-secondary hover:text-text-primary"
                >
                  Contact
                </Link>
              </li>
            </ul>
          </nav>
        </div>
        <div className="border-t border-border-subtle">
          <p className="mx-auto w-full max-w-6xl px-6 py-5 text-caption text-text-muted">
            © 2026 EduConnect. In active development — the Live Demo runs on
            sample data only.
          </p>
        </div>
      </footer>
    </div>
  );
}
