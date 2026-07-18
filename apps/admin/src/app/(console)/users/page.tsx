import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { UsersView } from "@/components/users/users-view";

export const metadata: Metadata = {
  title: "Users",
};

export default function UsersPage() {
  return (
    <RequireCapability anyOf={["authorization.roles-view"]}>
      <UsersView />
    </RequireCapability>
  );
}
