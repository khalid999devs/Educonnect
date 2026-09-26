import type { Metadata } from "next";

import { StudyView } from "@/components/study/study-view";

export const metadata: Metadata = {
  title: "Study",
  description:
    "Turn your own material into a summary, an explanation, a walkthrough, or practice questions.",
};

export default function StudyPage() {
  return <StudyView />;
}
