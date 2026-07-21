"use client";

import {
  EduConnectThemedLogo,
  Sidebar,
  SidebarItem,
  SidebarSection,
} from "@educonnect/ui";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { SidebarRhythm } from "./sidebar-rhythm";
import { isStudentNavItemActive, useStudentNav } from "./student-nav";

export function StudentSidebar() {
  const pathname = usePathname();
  const items = useStudentNav();

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
      footer={<SidebarRhythm />}
    >
      {/* One flat, ungrouped list. No `iconClassName` is passed, so every glyph
          inherits the row's text colour - muted at rest, white on the active
          row - giving the icons one consistent hue instead of a rainbow. */}
      <SidebarSection>
        {items.map((item) => {
          const isActive = isStudentNavItemActive(item, pathname);

          return (
            <SidebarItem
              key={item.href}
              label={item.label}
              icon={item.icon}
              href={item.href}
              isActive={isActive}
            />
          );
        })}
      </SidebarSection>
    </Sidebar>
  );
}
