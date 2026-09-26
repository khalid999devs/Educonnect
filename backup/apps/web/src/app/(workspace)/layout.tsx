import type { ReactNode } from "react";

import { RequireSession } from "@/components/auth/require-session";
import { QueryProvider } from "@/providers/query-provider";

/**
 * The focused workspace is a sibling of the app shell, not a child of it.
 *
 * A nested layout cannot remove an ancestor, so a workspace living under
 * `(app)` could only ever paint over the sidebar, topbar, and mobile nav. That
 * left the whole shell in the accessibility tree behind an opaque surface,
 * reachable by screen reader and by Tab. Route groups do not affect the URL,
 * so hosting the route here keeps `/second-brain/{id}/workspace` while giving
 * it a tree that genuinely contains only the workspace.
 *
 * Session and query context still have to be provided, because those came from
 * the `(app)` layout too. The chrome deliberately does not.
 *
 * `overflow-hidden` guarantees the page body never scrolls: each pane owns its
 * own scroll container.
 */
export default function WorkspaceLayout({ children }: { children: ReactNode }) {
  return (
    <RequireSession requireVerified>
      <QueryProvider>
        <div className="isolate h-dvh overflow-hidden bg-bg-canvas">
          {children}
        </div>
      </QueryProvider>
    </RequireSession>
  );
}
