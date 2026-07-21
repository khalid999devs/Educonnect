import type { Metadata } from "next";
import { Suspense } from "react";

import { TemplatesView } from "@/components/templates/templates-view";

export const metadata: Metadata = {
  title: "Templates",
};

/**
 * The view reads `?preference=` via `useSearchParams`, which opts the route
 * out of static rendering unless it sits inside a Suspense boundary.
 */
export default function TemplatesPage() {
  return (
    <Suspense fallback={null}>
      <TemplatesView />
    </Suspense>
  );
}
