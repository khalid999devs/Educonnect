import type { Metadata } from "next";

import { AuditView } from "@/components/audit/audit-view";
import { RequireCapability } from "@/components/auth/require-capability";

export const metadata: Metadata = {
  title: "Audit log",
};

export default function AuditPage() {
  return (
    <RequireCapability anyOf={["audit.view-all"]}>
      <AuditView />
    </RequireCapability>
  );
}
