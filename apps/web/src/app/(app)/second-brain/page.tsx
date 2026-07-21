import type { Metadata } from "next";
import { Suspense } from "react";

import { BrainView } from "@/components/second-brain/brain-view";

export const metadata: Metadata = {
  title: "Second Brain",
};

export default function SecondBrainPage() {
  /* BrainView mirrors the `?purpose=` filter into the URL, so it reads
     `useSearchParams` and needs a Suspense boundary. */
  return (
    <Suspense fallback={null}>
      <BrainView />
    </Suspense>
  );
}
