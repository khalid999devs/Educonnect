import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { DemoDataView } from "@/components/demo-data/demo-data-view";

export const metadata: Metadata = {
  title: "Demo data",
};

export default function DemoDataPage() {
  return (
    <RequireCapability anyOf={["content.curate"]}>
      <DemoDataView />
    </RequireCapability>
  );
}
