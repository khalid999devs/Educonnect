import { Card, EduConnectThemedLogo, ThemeToggle } from "@educonnect/ui";
import {
  BookOpen,
  CalendarDays,
  FolderOpen,
  Search,
  ShieldCheck,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import type { ReactNode } from "react";

const SIDE_CHIPS: Array<{
  icon: LucideIcon;
  title: string;
  sub: string;
  tint: string;
  float: string;
}> = [
  {
    icon: CalendarDays,
    title: "Plan",
    sub: "Stay on track",
    tint: "text-brand-primary",
    float: "motion-safe:animate-float",
  },
  {
    icon: BookOpen,
    title: "Learn",
    sub: "Smarter, not harder",
    tint: "text-status-info",
    float: "motion-safe:animate-float-delayed",
  },
  {
    icon: FolderOpen,
    title: "Organize",
    sub: "All in one place",
    tint: "text-status-success",
    float: "motion-safe:animate-float",
  },
  {
    icon: Search,
    title: "Research",
    sub: "Find what matters",
    tint: "text-status-research",
    float: "motion-safe:animate-float-delayed",
  },
];

/**
 * Shared frame for identity pages, following the onboarding reference art:
 * gradient-highlighted headline and floating capability chips on the left,
 * the form card on the right, privacy line at the bottom.
 */
export function AuthShell({
  headline,
  highlight,
  subtitle,
  title,
  children,
  footer,
}: {
  headline: string;
  highlight: string;
  subtitle: string;
  title: string;
  children: ReactNode;
  footer?: ReactNode;
}) {
  return (
    <div className="flex min-h-dvh flex-col bg-bg-canvas">
      <header className="flex items-center justify-between px-6 py-4">
        <Link
          href="/"
          aria-label="EduConnect home"
          className="inline-flex items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
        >
          <EduConnectThemedLogo width={150} decorative />
        </Link>
        <ThemeToggle />
      </header>

      <main className="relative mx-auto flex w-full max-w-5xl flex-1 items-center px-6 pb-12">
        <div
          aria-hidden="true"
          className="absolute -top-10 left-1/4 h-64 w-96 rounded-full bg-brand-primary/15 blur-3xl"
        />
        <div className="relative grid w-full items-center gap-12 lg:grid-cols-[1fr_420px]">
          <section className="hidden space-y-6 lg:block">
            <h1 className="text-display text-text-primary">
              {headline} <span className="text-brand-primary">{highlight}</span>
            </h1>
            <p className="max-w-md text-body-lg text-text-secondary">
              {subtitle}
            </p>
            <div className="grid max-w-md grid-cols-2 gap-3">
              {SIDE_CHIPS.map((chip) => (
                <div
                  key={chip.title}
                  className={`flex items-center gap-3 rounded-lg border border-border-default bg-bg-surface px-4 py-3 shadow-glow-sm ${chip.float}`}
                >
                  <chip.icon
                    aria-hidden="true"
                    className={`size-5 ${chip.tint}`}
                  />
                  <span>
                    <span className="block text-body font-semibold text-text-primary">
                      {chip.title}
                    </span>
                    <span className="block text-caption text-text-muted">
                      {chip.sub}
                    </span>
                  </span>
                </div>
              ))}
            </div>
          </section>

          <Card className="w-full p-7">
            <h2 className="text-h3 text-text-primary lg:hidden">
              {headline} <span className="text-brand-primary">{highlight}</span>
            </h2>
            <h2 className="hidden text-h3 text-text-primary lg:block">
              {title}
            </h2>
            <div className="mt-5">{children}</div>
            {footer ? (
              <div className="mt-5 border-t border-border-subtle pt-4 text-center text-body text-text-secondary">
                {footer}
              </div>
            ) : null}
          </Card>
        </div>
      </main>

      <p className="flex items-center justify-center gap-1.5 px-6 pb-6 text-caption text-text-muted">
        <ShieldCheck aria-hidden="true" className="size-3.5" />
        Private by default. Your academic data belongs to you.
      </p>
    </div>
  );
}
