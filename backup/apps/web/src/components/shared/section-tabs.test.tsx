import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { SectionTabs, type SectionTab } from "./section-tabs";

const TABS: readonly SectionTab<"posts" | "mentors" | "groups" | "people">[] = [
  { value: "posts", label: "Posts" },
  { value: "mentors", label: "Mentors" },
  { value: "groups", label: "Groups", count: 3 },
  { value: "people", label: "People" },
];

function setup(value: (typeof TABS)[number]["value"] = "posts") {
  const onChange = vi.fn();

  render(
    <SectionTabs
      tabs={TABS}
      value={value}
      onChange={onChange}
      label="Community sections"
      panelId={(tab) => `panel-${tab}`}
    />,
  );

  return { onChange };
}

describe("SectionTabs", () => {
  it("exposes a labelled tablist with one selected tab", () => {
    setup("mentors");

    expect(
      screen.getByRole("tablist", { name: "Community sections" }),
    ).toBeTruthy();
    expect(screen.getByRole("tab", { selected: true }).textContent).toContain(
      "Mentors",
    );
  });

  it("links each tab to its panel", () => {
    setup();

    expect(
      screen.getByRole("tab", { name: "Posts" }).getAttribute("aria-controls"),
    ).toBe("panel-posts");
  });

  it("keeps a single tab stop via roving tabIndex", () => {
    setup("groups");

    expect(
      screen.getByRole("tab", { name: /Groups/ }).getAttribute("tabindex"),
    ).toBe("0");
    expect(
      screen.getByRole("tab", { name: "Posts" }).getAttribute("tabindex"),
    ).toBe("-1");
  });

  it("selects on click", async () => {
    const { onChange } = setup();

    await userEvent.click(screen.getByRole("tab", { name: "People" }));

    expect(onChange).toHaveBeenCalledWith("people");
  });

  it("moves to the next tab on ArrowRight", async () => {
    const { onChange } = setup("posts");

    screen.getByRole("tab", { name: "Posts" }).focus();
    await userEvent.keyboard("{ArrowRight}");

    expect(onChange).toHaveBeenCalledWith("mentors");
  });

  it("wraps from the first tab to the last on ArrowLeft", async () => {
    const { onChange } = setup("posts");

    screen.getByRole("tab", { name: "Posts" }).focus();
    await userEvent.keyboard("{ArrowLeft}");

    expect(onChange).toHaveBeenCalledWith("people");
  });

  it("jumps to the ends with Home and End", async () => {
    const { onChange } = setup("groups");

    screen.getByRole("tab", { name: /Groups/ }).focus();
    await userEvent.keyboard("{Home}");
    expect(onChange).toHaveBeenCalledWith("posts");

    await userEvent.keyboard("{End}");
    expect(onChange).toHaveBeenCalledWith("people");
  });

  it("renders counts with tabular figures", () => {
    setup();

    const count = screen
      .getByRole("tab", { name: /Groups/ })
      .querySelector("span");

    expect(count?.textContent).toBe("3");
    expect(count?.className).toContain("tabular-nums");
  });
});
