import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { MentorsView } from "@/components/mentors/mentors-view";

export const metadata: Metadata = {
  title: "Mentors",
};

export default function MentorsPage() {
  return (
    <RequireCapability anyOf={["mentors.curate"]}>
      <MentorsView />
    </RequireCapability>
  );
}
