import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { ReportsView } from "@/components/reports/reports-view";

export const metadata: Metadata = {
  title: "Reports",
};

export default function ReportsPage() {
  return (
    <RequireCapability anyOf={["moderation.scoped", "moderation.global"]}>
      <ReportsView />
    </RequireCapability>
  );
}
