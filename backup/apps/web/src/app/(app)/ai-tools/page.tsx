import type { Metadata } from "next";
import { Suspense } from "react";

import { AiToolsView } from "@/components/ai-tools/ai-tools-view";

export const metadata: Metadata = {
  title: "AI Tools",
};

/**
 * The view reads `?preference=` via `useSearchParams`, which opts the route
 * out of static rendering unless it sits inside a Suspense boundary.
 */
export default function AiToolsPage() {
  return (
    <Suspense fallback={null}>
      <AiToolsView />
    </Suspense>
  );
}
