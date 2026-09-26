"use client";

import { MessagesSquare, Sparkles, UserRound, Users } from "lucide-react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback } from "react";

import { PageCover } from "@/components/shared/page-cover";
import { SectionTabs, type SectionTab } from "@/components/shared/section-tabs";
import { MentorsTab } from "@/components/mentors/mentors-tab";
import { GroupsTab } from "./groups-tab";
import { PeopleTab } from "./people-tab";
import { PostsTab } from "./posts-tab";

/**
 * The single social hub. Posts, Mentors, Groups, and People are tabs on one
 * surface rather than four half-populated pages, and mentors are surfaced here
 * once - the old "Featured mentors" aside duplicated the directory.
 *
 * The active tab lives in the `?tab=` search param, which is what makes the
 * detail routes (`/community/groups/{id}`, `/community/posts/{id}`,
 * `/community/mentors/{id}`) able to send you back to the tab you came from.
 * `posts` is the default and is written as the absence of the param, so the
 * canonical `/community` URL stays clean.
 */
const TABS = [
  { value: "posts", label: "Posts", icon: MessagesSquare },
  { value: "mentors", label: "Mentors", icon: Sparkles },
  { value: "groups", label: "Groups", icon: Users },
  { value: "people", label: "People", icon: UserRound },
] as const satisfies readonly SectionTab<CommunityTab>[];

export type CommunityTab = "posts" | "mentors" | "groups" | "people";

const TAB_VALUES: readonly string[] = ["posts", "mentors", "groups", "people"];

export function isCommunityTab(value: string | null): value is CommunityTab {
  return value !== null && TAB_VALUES.includes(value);
}

const PANEL_COPY: Record<CommunityTab, { title: string; subtitle: string }> = {
  posts: {
    title: "Your community feed",
    subtitle:
      "Questions, resources, and progress from every group you have joined.",
  },
  mentors: {
    title: "Find a mentor",
    subtitle:
      "Search the directory, filter by expertise, and ask for help directly. No bookings, no payments.",
  },
  groups: {
    title: "Curated groups",
    subtitle:
      "Join a space to unlock its feed and meet the people studying alongside you.",
  },
  people: {
    title: "People in your groups",
    subtitle:
      "Everyone who shares a group with you. EduConnect has no follower counts and no friend graph.",
  },
};

function panelId(value: CommunityTab): string {
  return `community-panel-${value}`;
}

export function CommunityView() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const requested = searchParams.get("tab");
  const tab: CommunityTab = isCommunityTab(requested) ? requested : "posts";

  const setTab = useCallback(
    (next: CommunityTab) => {
      const params = new URLSearchParams(searchParams.toString());

      if (next === "posts") {
        params.delete("tab");
      } else {
        params.set("tab", next);
      }

      const query = params.toString();

      router.replace(query === "" ? pathname : `${pathname}?${query}`, {
        scroll: false,
      });
    },
    [pathname, router, searchParams],
  );

  const copy = PANEL_COPY[tab];

  return (
    <div className="mx-auto flex w-full max-w-360 flex-col gap-5">
      <PageCover
        photo="/marketing/library-curve.jpg"
        title={copy.title}
        subtitle={copy.subtitle}
        headingLevel={1}
        tall
        priority
      />

      <SectionTabs
        tabs={TABS}
        value={tab}
        onChange={setTab}
        label="Community sections"
        panelId={panelId}
        className="motion-safe:animate-fade-up"
      />

      <div
        role="tabpanel"
        id={panelId(tab)}
        aria-labelledby={`section-tab-${tab}`}
        tabIndex={0}
        className="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
      >
        {tab === "posts" ? <PostsTab /> : null}
        {tab === "mentors" ? <MentorsTab /> : null}
        {tab === "groups" ? <GroupsTab /> : null}
        {tab === "people" ? <PeopleTab /> : null}
      </div>
    </div>
  );
}
