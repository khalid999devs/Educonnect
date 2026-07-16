import {
  Bookmark,
  Brain,
  CalendarDays,
  FlaskConical,
  GraduationCap,
  HeartHandshake,
  Inbox,
  LayoutDashboard,
  LayoutTemplate,
  Library,
  ListChecks,
  Settings,
  TrendingUp,
  Users,
  Wrench,
  type LucideIcon,
} from "lucide-react";

export type StudentNavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
  /**
   * Only destinations that exist in this phase are presented as available;
   * everything else renders as a disabled "Soon" entry (doc 07: never show
   * future features as available).
   */
  available: boolean;
};

export type StudentNavSection = {
  title?: string;
  items: StudentNavItem[];
};

export const STUDENT_NAV: StudentNavSection[] = [
  {
    items: [
      {
        label: "Dashboard",
        href: "/dashboard",
        icon: LayoutDashboard,
        available: true,
      },
      { label: "Smart Intake", href: "/intake", icon: Inbox, available: false },
    ],
  },
  {
    title: "Plan",
    items: [
      {
        label: "Planner",
        href: "/planner",
        icon: CalendarDays,
        available: false,
      },
      {
        label: "Courses",
        href: "/courses",
        icon: GraduationCap,
        available: false,
      },
      { label: "Tasks", href: "/tasks", icon: ListChecks, available: false },
    ],
  },
  {
    title: "Library",
    items: [
      {
        label: "Resources",
        href: "/resources",
        icon: Library,
        available: false,
      },
      {
        label: "Tools & Prompts",
        href: "/tools-prompts",
        icon: Wrench,
        available: false,
      },
      {
        label: "Templates",
        href: "/templates",
        icon: LayoutTemplate,
        available: false,
      },
    ],
  },
  {
    title: "Knowledge",
    items: [
      {
        label: "Second Brain",
        href: "/second-brain",
        icon: Brain,
        available: false,
      },
      {
        label: "Research",
        href: "/research",
        icon: FlaskConical,
        available: false,
      },
    ],
  },
  {
    title: "Connect",
    items: [
      {
        label: "Community",
        href: "/community",
        icon: Users,
        available: false,
      },
      {
        label: "Mentors",
        href: "/mentors",
        icon: HeartHandshake,
        available: false,
      },
    ],
  },
  {
    title: "You",
    items: [
      {
        label: "Progress",
        href: "/progress",
        icon: TrendingUp,
        available: false,
      },
      { label: "Saved", href: "/saved", icon: Bookmark, available: false },
      {
        label: "Settings",
        href: "/settings",
        icon: Settings,
        available: false,
      },
    ],
  },
];

/** Core destinations for the <768px bottom navigation (doc 04). */
export const STUDENT_MOBILE_NAV: StudentNavItem[] = [
  {
    label: "Dashboard",
    href: "/dashboard",
    icon: LayoutDashboard,
    available: true,
  },
  { label: "Intake", href: "/intake", icon: Inbox, available: false },
  { label: "Planner", href: "/planner", icon: CalendarDays, available: false },
  { label: "Brain", href: "/second-brain", icon: Brain, available: false },
  { label: "Tools", href: "/tools-prompts", icon: Wrench, available: false },
];
