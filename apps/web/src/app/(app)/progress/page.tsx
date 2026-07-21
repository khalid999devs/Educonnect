import type { Metadata } from "next";

import { ProgressView } from "@/components/progress/progress-view";

export const metadata: Metadata = {
  title: "Progress",
  description:
    "Your progress computed from your own records. No streaks, badges, or vanity metrics.",
};

export default function ProgressPage() {
  return <ProgressView />;
}
