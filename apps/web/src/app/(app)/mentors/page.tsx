import type { Metadata } from "next";

import { MentorsView } from "@/components/mentors/mentors-view";

export const metadata: Metadata = {
  title: "Mentors",
};

export default function MentorsPage() {
  return <MentorsView />;
}
