import type { Metadata } from "next";

import { ItemDetail } from "@/components/second-brain/item-detail";

export const metadata: Metadata = {
  title: "Knowledge item",
};

export default async function SecondBrainItemPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <ItemDetail itemId={id} />;
}
