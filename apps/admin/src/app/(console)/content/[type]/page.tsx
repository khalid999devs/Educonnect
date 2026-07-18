import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { RequireCapability } from "@/components/auth/require-capability";
import { ContentCurationView } from "@/components/content/content-curation-view";
import type { ContentType } from "@/lib/api/admin-content";

export const metadata: Metadata = {
  title: "Content",
};

const TYPES: ContentType[] = ["tools", "prompts", "workflows"];

export default async function ContentPage({
  params,
}: {
  params: Promise<{ type: string }>;
}) {
  const { type } = await params;

  if (!TYPES.includes(type as ContentType)) {
    notFound();
  }

  return (
    <RequireCapability anyOf={["content.curate"]}>
      <ContentCurationView type={type as ContentType} />
    </RequireCapability>
  );
}
