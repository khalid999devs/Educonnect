import type { Metadata } from "next";
import { Suspense } from "react";

import { WorkspaceView } from "@/components/second-brain/workspace/workspace-view";

export const metadata: Metadata = {
  title: "Focused workspace",
};

export default async function SecondBrainWorkspacePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  /* WorkspaceView reads the optional `?intake=` parameter, so it needs a
     Suspense boundary for `useSearchParams`. */
  return (
    <Suspense fallback={null}>
      <WorkspaceView itemId={id} />
    </Suspense>
  );
}
