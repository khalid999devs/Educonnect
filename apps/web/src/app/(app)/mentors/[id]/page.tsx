import type { Metadata } from "next";

import { MentorDetail } from "@/components/mentors/mentor-detail";

export const metadata: Metadata = {
  title: "Mentor",
};

export default async function MentorPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <MentorDetail mentorId={id} />;
}
