import type { Metadata } from "next";

import { TopicDetail } from "@/components/research/topic-detail";

export const metadata: Metadata = {
  title: "Research topic",
};

export default async function ResearchTopicPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <TopicDetail topicId={id} />;
}
