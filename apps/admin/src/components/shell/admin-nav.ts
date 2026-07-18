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
  /**
   * Capability (or any-of list) required to use the module. An available module
   * the signed-in admin lacks the capability for renders disabled — the API
   * remains authoritative regardless.
   */
  capability?: string | string[];
};

export type AdminNavSection = {
  title?: string;
  items: AdminNavItem[];
};

/**
 * A module is usable when it has shipped AND the signed-in admin holds one of
 * its required capabilities. Navigation shaping only — the API re-checks every
 * request.
 */
export function navItemEnabled(
  item: AdminNavItem,
  can: (capability: string) => boolean,
): boolean {
  if (!item.available) {
    return false;
  }

  if (item.capability === undefined) {
    return true;
  }

  const capabilities = Array.isArray(item.capability)
    ? item.capability
    : [item.capability];

  return capabilities.some((capability) => can(capability));
}

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
      {
        label: "Users",
        href: "/users",
        icon: Users,
        available: true,
        capability: "authorization.roles-view",
      },
      {
        label: "Roles",
        href: "/roles",
        icon: ShieldCheck,
        available: true,
        capability: "authorization.roles-view",
      },
      {
        label: "Mentors",
        href: "/mentors",
        icon: HeartHandshake,
        available: true,
        capability: "mentors.curate",
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
      {
        label: "Reports",
        href: "/reports",
        icon: Flag,
        available: true,
        capability: ["moderation.scoped", "moderation.global"],
      },
      {
        label: "Audit log",
        href: "/audit",
        icon: ScrollText,
        available: true,
        capability: "audit.view-all",
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
