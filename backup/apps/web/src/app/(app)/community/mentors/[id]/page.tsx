import type { Metadata } from "next";

import { MentorDetail } from "@/components/mentors/mentor-detail";

export const metadata: Metadata = {
  title: "Mentor",
};

/** Mentors are a Community tab, so a mentor profile is a Community detail
 * route. `/mentors/{id}` stays reachable until W4-E2 redirects it here. */
export default async function CommunityMentorPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <MentorDetail mentorId={id} />;
}
