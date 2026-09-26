import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { CommunitiesView } from "@/components/communities/communities-view";

export const metadata: Metadata = {
  title: "Communities",
};

export default function CommunitiesPage() {
  return (
    <RequireCapability anyOf={["content.curate"]}>
      <CommunitiesView />
    </RequireCapability>
  );
}
