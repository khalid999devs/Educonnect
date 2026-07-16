import { render, screen, within } from "@testing-library/react";
import { LayoutDashboard, ListTodo } from "lucide-react";
import type { ComponentProps, ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";

import { MobileNav, MobileNavItem } from "./mobile-nav";
import { Sidebar, SidebarItem, SidebarSection } from "./sidebar";

vi.mock("next/link", () => ({
  default: ({
    href,
    children,
    ...anchorProps
  }: ComponentProps<"a"> & { children: ReactNode }) => (
    <a href={typeof href === "string" ? href : undefined} {...anchorProps}>
      {children}
    </a>
  ),
}));

describe("Sidebar", () => {
  it("renders a labelled navigation landmark with the active page marked", () => {
    render(
      <Sidebar label="Student navigation">
        <SidebarSection title="Overview">
          <SidebarItem
            label="Dashboard"
            icon={LayoutDashboard}
            href="/dashboard"
            isActive
          />
          <SidebarItem label="Tasks" icon={ListTodo} disabled />
        </SidebarSection>
      </Sidebar>,
    );

    const nav = screen.getByRole("navigation", { name: "Student navigation" });
    const active = within(nav).getByRole("link", { name: "Dashboard" });

    expect(active).toHaveAttribute("aria-current", "page");
    expect(active).toHaveAttribute("href", "/dashboard");
  });

  it("renders planned destinations as non-links marked Soon", () => {
    render(
      <Sidebar label="Student navigation">
        <SidebarSection>
          <SidebarItem label="Tasks" icon={ListTodo} disabled />
        </SidebarSection>
      </Sidebar>,
    );

    expect(
      screen.queryByRole("link", { name: /Tasks/ }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByText("Tasks").closest("span[aria-disabled]"),
    ).not.toBeNull();
    expect(screen.getByText("Soon")).toBeInTheDocument();
  });
});

describe("MobileNav", () => {
  it("marks the active core destination", () => {
    render(
      <MobileNav label="Core navigation">
        <MobileNavItem
          label="Dashboard"
          icon={LayoutDashboard}
          href="/dashboard"
          isActive
        />
        <MobileNavItem label="Planner" icon={ListTodo} disabled />
      </MobileNav>,
    );

    const nav = screen.getByRole("navigation", { name: "Core navigation" });

    expect(
      within(nav).getByRole("link", { name: "Dashboard" }),
    ).toHaveAttribute("aria-current", "page");
    expect(
      within(nav).queryByRole("link", { name: "Planner" }),
    ).not.toBeInTheDocument();
  });
});
