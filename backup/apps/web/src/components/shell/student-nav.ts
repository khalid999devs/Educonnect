"use client";

import { useQuery } from "@tanstack/react-query";
import {
  Brain,
  CalendarDays,
  HeartHandshake,
  LayoutDashboard,
  LayoutTemplate,
  Library,
  Settings,
  Sparkles,
  TrendingUp,
  Users,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import { useMemo } from "react";

import {
  CONNECTED_MENTOR_STATUSES,
  hasAcceptedMentor,
} from "@/lib/api/mentors";
import { mentorKeys } from "@/lib/query-keys";
import { useSession } from "@/providers/session-provider";

/**
 * Student navigation: ten destinations in one flat list, plus Mentors for the
 * students who actually have a mentor connection.
 *
 * The list is deliberately ungrouped - ten reachable rows do not need section
 * headers, and the icons carry one consistent hue (they inherit the row's text
 * colour) rather than a per-section rainbow, so the labels lead and the active
 * row is the only strong colour on the rail.
 *
 * There is deliberately no `available` flag any more. Doc 07 forbids showing a
 * future feature as if it were available, and the answer to that is to not list
 * it at all - a permanently greyed "Soon" row is the same broken promise with
 * extra steps. Every entry below is built and reachable.
 */
export type StudentNavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
  /**
   * Pathname prefixes that light this item up. Kept separate from `href`
   * because a destination is not always its own route: Mentors links into
   * `/community?tab=mentors` but owns the `/community/mentors/...` detail
   * pages. Matching on pathnames only keeps the shell free of
   * `useSearchParams`, which would force every app route to client rendering.
   */
  activePaths: string[];
  /** Prefixes that belong to a sibling item and must not light this one. */
  excludePaths?: string[];
};

/** True when `pathname` is the prefix itself or nested beneath it. */
function matchesPrefix(pathname: string, prefix: string): boolean {
  return pathname === prefix || pathname.startsWith(`${prefix}/`);
}

/**
 * Active-route matching for one nav entry, including nested routes. Exported so
 * the sidebar and the bottom bar cannot drift into two different rules.
 */
export function isStudentNavItemActive(
  item: StudentNavItem,
  pathname: string,
): boolean {
  if (item.excludePaths?.some((path) => matchesPrefix(pathname, path))) {
    return false;
  }

  return item.activePaths.some((path) => matchesPrefix(pathname, path));
}

const HOME: StudentNavItem = {
  label: "Home",
  href: "/dashboard",
  icon: LayoutDashboard,
  activePaths: ["/dashboard"],
};

const SECOND_BRAIN: StudentNavItem = {
  label: "Second Brain",
  href: "/second-brain",
  icon: Brain,
  activePaths: ["/second-brain"],
};

const STUDY: StudentNavItem = {
  label: "Study",
  href: "/study",
  icon: Sparkles,
  activePaths: ["/study"],
};

const PLANNER: StudentNavItem = {
  label: "Planner",
  href: "/planner",
  icon: CalendarDays,
  activePaths: ["/planner"],
};

const RESOURCES: StudentNavItem = {
  label: "Resources",
  href: "/resources",
  icon: Library,
  activePaths: ["/resources"],
};

const AI_TOOLS: StudentNavItem = {
  label: "AI Tools",
  href: "/ai-tools",
  icon: Wrench,
  activePaths: ["/ai-tools"],
};

const TEMPLATES: StudentNavItem = {
  label: "Templates",
  href: "/templates",
  icon: LayoutTemplate,
  activePaths: ["/templates"],
};

const COMMUNITY: StudentNavItem = {
  label: "Community",
  href: "/community",
  icon: Users,
  activePaths: ["/community"],
  /* The mentor detail pages belong to the Mentors entry, not to Community. */
  excludePaths: ["/community/mentors"],
};

/**
 * Mentors is a tab of the Community hub, so it links into the hub with the tab
 * preselected and claims the mentor detail routes as its own.
 */
const MENTORS: StudentNavItem = {
  label: "Mentors",
  href: "/community?tab=mentors",
  icon: HeartHandshake,
  activePaths: ["/community/mentors"],
};

const PROGRESS: StudentNavItem = {
  label: "Progress",
  href: "/progress",
  icon: TrendingUp,
  activePaths: ["/progress"],
};

const SETTINGS: StudentNavItem = {
  label: "Settings",
  href: "/settings",
  icon: Settings,
  activePaths: ["/settings"],
};

function buildNav(withMentors: boolean): StudentNavItem[] {
  return [
    HOME,
    SECOND_BRAIN,
    STUDY,
    PLANNER,
    RESOURCES,
    AI_TOOLS,
    TEMPLATES,
    COMMUNITY,
    ...(withMentors ? [MENTORS] : []),
    PROGRESS,
    SETTINGS,
  ];
}

const NAV_WITHOUT_MENTORS = buildNav(false);
const NAV_WITH_MENTORS = buildNav(true);

/** How long the mentor gate stays fresh. A connection is not a per-view fact. */
const MENTOR_GATE_STALE_MS = 5 * 60 * 1000;

/**
 * The student navigation for the signed-in user.
 *
 * Mentors is per-user, so the nav cannot be a module const. While the gate is
 * still loading the flag is `undefined` and the nav renders *without* Mentors:
 * appearing late is a far cheaper mistake than appearing and then being taken
 * away, and a skeleton nav row would be a third state that flickers twice.
 */
export function useStudentNav(): StudentNavItem[] {
  const { status } = useSession();

  const { data: hasMentorConnection } = useQuery({
    queryKey: mentorKeys.sentRequests({
      status: CONNECTED_MENTOR_STATUSES.join(","),
      per_page: 1,
      gate: true,
    }),
    queryFn: hasAcceptedMentor,
    enabled: status === "authenticated",
    staleTime: MENTOR_GATE_STALE_MS,
  });

  return useMemo(
    () =>
      hasMentorConnection === true ? NAV_WITH_MENTORS : NAV_WITHOUT_MENTORS,
    [hasMentorConnection],
  );
}

/**
 * The five destinations of the <768px bottom bar (doc 04).
 *
 * Chosen for daily reach, not for parity with the sidebar: Home is the entry
 * point, Second Brain is where capture happens on a phone (it takes the slot
 * the old Smart Intake tab held), Study and Planner are the two things a
 * student opens between classes, and Resources is the most common lookup.
 * AI Tools, Templates, Community, Progress and Settings are exploratory or
 * occasional and stay one tap away from Home. Mentors is deliberately absent:
 * a conditional entry in a fixed five-slot bar would reflow the whole bar under
 * the user's thumb the moment the gate resolves.
 */
export const STUDENT_MOBILE_NAV: StudentNavItem[] = [
  HOME,
  /* "Second Brain" wraps in a five-column bar at 320px; the icon carries it. */
  { ...SECOND_BRAIN, label: "Brain" },
  STUDY,
  PLANNER,
  RESOURCES,
];
