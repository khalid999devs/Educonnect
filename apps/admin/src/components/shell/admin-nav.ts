import {
  Database,
  Flag,
  HeartHandshake,
  LayoutDashboard,
  LayoutTemplate,
  Library,
  ListChecks,
  MessageSquare,
  ScrollText,
  Settings,
  ShieldCheck,
  Sparkles,
  TrendingUp,
  Users,
  Workflow,
  Wrench,
  type LucideIcon,
} from "lucide-react";

export type AdminNavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
  /** Modules render disabled until their phase ships them (doc 08 list). */
  available: boolean;
};

export type AdminNavSection = {
  title?: string;
  items: AdminNavItem[];
};

export const ADMIN_NAV: AdminNavSection[] = [
  {
    items: [
      {
        label: "Overview",
        href: "/",
        icon: LayoutDashboard,
        available: true,
      },
    ],
  },
  {
    title: "People",
    items: [
      { label: "Users", href: "/users", icon: Users, available: false },
      { label: "Roles", href: "/roles", icon: ShieldCheck, available: false },
      {
        label: "Mentors",
        href: "/mentors",
        icon: HeartHandshake,
        available: false,
      },
    ],
  },
  {
    title: "Content",
    items: [
      { label: "Tools", href: "/tools", icon: Wrench, available: false },
      {
        label: "Prompts",
        href: "/prompts",
        icon: MessageSquare,
        available: false,
      },
      {
        label: "Workflows",
        href: "/workflows",
        icon: Workflow,
        available: false,
      },
      {
        label: "Templates",
        href: "/templates",
        icon: LayoutTemplate,
        available: false,
      },
      {
        label: "Resources",
        href: "/resources",
        icon: Library,
        available: false,
      },
      {
        label: "Communities",
        href: "/communities",
        icon: Users,
        available: false,
      },
    ],
  },
  {
    title: "Safety",
    items: [
      { label: "Reports", href: "/reports", icon: Flag, available: false },
      {
        label: "Audit log",
        href: "/audit",
        icon: ScrollText,
        available: false,
      },
    ],
  },
  {
    title: "Operations",
    items: [
      {
        label: "Demo data",
        href: "/demo-data",
        icon: Database,
        available: false,
      },
      {
        label: "Analytics",
        href: "/analytics",
        icon: TrendingUp,
        available: false,
      },
      {
        label: "AI usage",
        href: "/ai-usage",
        icon: Sparkles,
        available: false,
      },
      { label: "Jobs", href: "/jobs", icon: ListChecks, available: false },
      {
        label: "Settings",
        href: "/settings",
        icon: Settings,
        available: false,
      },
    ],
  },
];
