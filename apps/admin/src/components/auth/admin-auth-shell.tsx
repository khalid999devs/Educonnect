import { Card, EduConnectThemedLogo, ThemeToggle } from "@educonnect/ui";
import { Lock, ShieldCheck } from "lucide-react";
import type { ReactNode } from "react";

/**
 * Sign-in frame for the console. Deliberately austere — doc 08 asks admin
 * surfaces to prioritise accuracy, density, and safety over the decorative
 * covers used on student pages. A single focused card, a restricted-access
 * note, and no marketing copy.
 */
export function AdminAuthShell({
  title,
  description,
  children,
}: {
  title: string;
  description: string;
  children: ReactNode;
}) {
  return (
    <div className="flex min-h-dvh flex-col bg-bg-canvas">
      <header className="flex items-center justify-between px-6 py-4">
        <div className="inline-flex items-center gap-2">
          <EduConnectThemedLogo width={140} decorative />
          <span className="rounded-md border border-border-default px-2 py-0.5 text-caption font-semibold uppercase tracking-wide text-text-muted">
            Console
          </span>
        </div>
        <ThemeToggle />
      </header>

      <main className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 pb-16">
        <Card className="w-full p-7">
          <div className="flex items-center gap-2 text-text-muted">
            <Lock aria-hidden="true" className="size-4" />
            <span className="text-caption font-medium uppercase tracking-wide">
              Restricted access
            </span>
          </div>
          <h1 className="mt-3 text-h3 text-text-primary">{title}</h1>
          <p className="mt-1 text-body text-text-secondary">{description}</p>
          <div className="mt-6">{children}</div>
        </Card>

        <p className="mt-6 flex items-center justify-center gap-1.5 text-caption text-text-muted">
          <ShieldCheck aria-hidden="true" className="size-3.5" />
          Administrative access is authenticated, least-privilege, and audited.
        </p>
      </main>
    </div>
  );
}
