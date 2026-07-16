import { ThemeToggle, TopBar } from "@educonnect/ui";
import type { ReactNode } from "react";

import { AdminSidebar } from "@/components/shell/admin-sidebar";

export default function ConsoleShellLayout({
  children,
}: {
  children: ReactNode;
}) {
  return (
    <div className="min-h-dvh bg-bg-canvas">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-bg-surface focus:px-4 focus:py-2 focus:text-body focus:text-text-primary"
      >
        Skip to main content
      </a>

      <div className="fixed inset-y-0 left-0 z-40 hidden lg:block">
        <AdminSidebar />
      </div>

      <div className="lg:pl-60">
        <TopBar density="compact" trailing={<ThemeToggle />}>
          <p className="truncate text-body text-text-muted">
            Administration console shell
          </p>
        </TopBar>
        <main id="main" className="mx-auto w-full max-w-360 px-4 py-6 sm:px-6">
          {children}
        </main>
      </div>
    </div>
  );
}
