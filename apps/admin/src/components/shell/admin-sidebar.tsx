"use client";

import {
  Badge,
  EduConnectThemedLogo,
  Sidebar,
  SidebarItem,
  SidebarSection,
} from "@educonnect/ui";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { useSession } from "@/providers/session-provider";

import { ADMIN_NAV, navItemEnabled } from "./admin-nav";

export function AdminSidebar() {
  const pathname = usePathname();
  const { can } = useSession();

  return (
    <Sidebar
      label="Console navigation"
      header={
        <Link
          href="/"
          aria-label="Go to the console overview"
          className="inline-flex items-center gap-2 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
        >
          <EduConnectThemedLogo width={128} decorative />
          <Badge variant="brand">Console</Badge>
        </Link>
      }
      footer={
        <p className="text-caption text-text-muted">
          Admin modules unlock for your capabilities as their APIs ship.
        </p>
      }
    >
      {ADMIN_NAV.map((section, index) => (
        <SidebarSection key={section.title ?? index} title={section.title}>
          {section.items.map((item) => (
            <SidebarItem
              key={item.href}
              label={item.label}
              icon={item.icon}
              href={item.href}
              disabled={!navItemEnabled(item, can)}
              isActive={
                item.href === "/"
                  ? pathname === "/"
                  : pathname === item.href ||
                    pathname.startsWith(`${item.href}/`)
              }
            />
          ))}
        </SidebarSection>
      ))}
    </Sidebar>
  );
}
