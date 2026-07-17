import type { Metadata } from "next";

import { BrainView } from "@/components/second-brain/brain-view";

export const metadata: Metadata = {
  title: "Second Brain",
};

export default function SecondBrainPage() {
  return <BrainView />;
}
