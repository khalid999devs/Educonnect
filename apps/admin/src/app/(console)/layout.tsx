import { ThemeToggle, TopBar } from "@educonnect/ui";
import type { ReactNode } from "react";

import { RequireAdmin } from "@/components/auth/require-admin";
import { AdminIdentity } from "@/components/shell/admin-identity";
import { AdminSidebar } from "@/components/shell/admin-sidebar";
import { QueryProvider } from "@/providers/query-provider";
import { StepUpProvider } from "@/providers/step-up-provider";

export default function ConsoleShellLayout({
  children,
}: {
  children: ReactNode;
}) {
  return (
    <RequireAdmin>
      <QueryProvider>
        <StepUpProvider>
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
              <TopBar
                density="compact"
                trailing={
                  <>
                    <AdminIdentity />
                    <ThemeToggle />
                  </>
                }
              >
                <p className="truncate text-body text-text-muted">
                  Administration console
                </p>
              </TopBar>
              <main
                id="main"
                className="mx-auto w-full max-w-360 px-4 py-6 sm:px-6"
              >
                {children}
              </main>
            </div>
          </div>
        </StepUpProvider>
      </QueryProvider>
    </RequireAdmin>
  );
}
