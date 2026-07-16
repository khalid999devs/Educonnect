"use client";

import {
  EduConnectThemedLogo,
  Sidebar,
  SidebarItem,
  SidebarSection,
} from "@educonnect/ui";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { STUDENT_NAV } from "./student-nav";

export function StudentSidebar() {
  const pathname = usePathname();

  return (
    <Sidebar
      label="Student navigation"
      header={
        <Link
          href="/dashboard"
          aria-label="Go to your dashboard"
          className="inline-flex items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
        >
          <EduConnectThemedLogo width={150} decorative />
        </Link>
      }
      footer={
        <p className="text-caption text-text-muted">
          Application shell (Phase 18). Marked areas unlock in later phases.
        </p>
      }
    >
      {STUDENT_NAV.map((section, index) => (
        <SidebarSection key={section.title ?? index} title={section.title}>
          {section.items.map((item) => (
            <SidebarItem
              key={item.href}
              label={item.label}
              icon={item.icon}
              href={item.href}
              disabled={!item.available}
              isActive={
                pathname === item.href || pathname.startsWith(`${item.href}/`)
              }
            />
          ))}
        </SidebarSection>
      ))}
    </Sidebar>
  );
}
