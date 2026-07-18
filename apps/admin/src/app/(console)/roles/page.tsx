import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { RolesView } from "@/components/roles/roles-view";

export const metadata: Metadata = {
  title: "Roles",
};

export default function RolesPage() {
  return (
    <RequireCapability anyOf={["authorization.roles-view"]}>
      <RolesView />
    </RequireCapability>
  );
}
