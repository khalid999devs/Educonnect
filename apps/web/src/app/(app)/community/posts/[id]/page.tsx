import type { Metadata } from "next";

import { PostDetail } from "@/components/community/post-detail";

export const metadata: Metadata = {
  title: "Community post",
};

export default async function CommunityPostPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <PostDetail postId={id} />;
}
