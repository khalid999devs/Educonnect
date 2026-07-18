import type { Metadata } from "next";

import { RequireCapability } from "@/components/auth/require-capability";
import { UserDetailView } from "@/components/users/user-detail-view";

export const metadata: Metadata = {
  title: "User",
};

export default async function UserDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <RequireCapability anyOf={["authorization.roles-view"]}>
      <UserDetailView userId={id} />
    </RequireCapability>
  );
}
