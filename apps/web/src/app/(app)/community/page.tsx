import type { Metadata } from "next";
import { Suspense } from "react";

import { CommunityView } from "@/components/community/community-view";

export const metadata: Metadata = {
  title: "Community",
};

/** The hub reads `?tab=` with `useSearchParams`, so it renders inside a
 * Suspense boundary: without one, Next opts the whole route out of static
 * rendering at build time. */
export default function CommunityPage() {
  return (
    <Suspense fallback={null}>
      <CommunityView />
    </Suspense>
  );
}
