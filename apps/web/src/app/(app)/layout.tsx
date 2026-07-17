import { ThemeToggle, TopBar } from "@educonnect/ui";
import type { ReactNode } from "react";

import { RequireSession } from "@/components/auth/require-session";
import { TopbarUser } from "@/components/shell/topbar-user";
import { AppCopilot } from "@/components/shell/app-copilot";
import { StudentMobileNav } from "@/components/shell/student-mobile-nav";
import { StudentSidebar } from "@/components/shell/student-sidebar";
import { QueryProvider } from "@/providers/query-provider";

export default function AppShellLayout({ children }: { children: ReactNode }) {
  return (
    <RequireSession requireVerified>
      <QueryProvider>
        <div className="min-h-dvh bg-bg-canvas">
          <a
            href="#main"
            className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-bg-surface focus:px-4 focus:py-2 focus:text-body focus:text-text-primary"
          >
            Skip to main content
          </a>

          <div className="fixed inset-y-0 left-0 z-40 hidden lg:block">
            <StudentSidebar />
          </div>

          <div className="lg:pl-60">
            <TopBar
              trailing={
                <>
                  <TopbarUser />
                  <ThemeToggle />
                </>
              }
            >
              <p className="truncate text-body text-text-muted">
                Student app shell
              </p>
            </TopBar>
            <main
              id="main"
              className="mx-auto w-full max-w-360 px-4 py-6 pb-28 sm:px-6 md:pb-8"
            >
              {children}
            </main>
          </div>

          <StudentMobileNav />
          <AppCopilot />
        </div>
      </QueryProvider>
    </RequireSession>
  );
}
