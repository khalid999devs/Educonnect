"use client";

import { MobileNav, MobileNavItem } from "@educonnect/ui";
import { usePathname } from "next/navigation";

import { STUDENT_MOBILE_NAV } from "./student-nav";

export function StudentMobileNav() {
  const pathname = usePathname();

  return (
    <MobileNav label="Core navigation">
      {STUDENT_MOBILE_NAV.map((item) => (
        <MobileNavItem
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
    </MobileNav>
  );
}
