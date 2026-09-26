import type { Metadata } from "next";

import { AnalyticsView } from "@/components/analytics/analytics-view";
import { RequireCapability } from "@/components/auth/require-capability";

export const metadata: Metadata = {
  title: "Analytics",
};

export default function AnalyticsPage() {
  return (
    <RequireCapability anyOf={["audit.view-all"]}>
      <AnalyticsView />
    </RequireCapability>
  );
}
