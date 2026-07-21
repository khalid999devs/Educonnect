import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import {
  readCatalogPreference,
  isCatalogPreference,
} from "./catalog-preference";
import { SavedFilterChip } from "./saved-filter-chip";

describe("SavedFilterChip", () => {
  it("is a toggle, so it reports its state with aria-pressed", () => {
    const { rerender } = render(
      <SavedFilterChip
        accent="templates"
        active={false}
        onToggle={() => {}}
        describes="templates"
      />,
    );

    expect(screen.getByRole("button").getAttribute("aria-pressed")).toBe(
      "false",
    );

    rerender(
      <SavedFilterChip
        accent="templates"
        active
        onToggle={() => {}}
        describes="templates"
      />,
    );

    expect(screen.getByRole("button").getAttribute("aria-pressed")).toBe(
      "true",
    );
  });

  it("carries the section accent as literal classes when active", () => {
    render(
      <SavedFilterChip
        accent="aiTools"
        active
        onToggle={() => {}}
        describes="tools"
      />,
    );

    expect(screen.getByRole("button").className).toContain("bg-status-ai/12");
  });

  it("uses a fixed amber treatment in the bookmark tone, in both states", () => {
    const { rerender } = render(
      <SavedFilterChip
        tone="bookmark"
        active
        onToggle={() => {}}
        describes="knowledge items"
      />,
    );

    const activeButton = screen.getByRole("button");
    expect(activeButton.className).toContain("bg-status-deadline/15");
    expect(activeButton.className).toContain("border-status-deadline/55");
    // The section accent must not leak in: bookmark is a distinct affordance.
    expect(activeButton.className).not.toContain("status-research");
    // The bookmark glyph is amber in either state.
    expect(activeButton.querySelector("svg")?.getAttribute("class")).toContain(
      "text-status-deadline",
    );

    rerender(
      <SavedFilterChip
        tone="bookmark"
        active={false}
        onToggle={() => {}}
        describes="knowledge items"
      />,
    );

    // Amber shows even when inactive, so its identity never reads as another
    // neutral filter chip.
    const inactiveButton = screen.getByRole("button");
    expect(inactiveButton.className).toContain("border-status-deadline/40");
    expect(
      inactiveButton.querySelector("svg")?.getAttribute("class"),
    ).toContain("text-status-deadline");
  });

  it("calls back once per click", () => {
    const onToggle = vi.fn();

    render(
      <SavedFilterChip
        accent="aiTools"
        active={false}
        onToggle={onToggle}
        describes="tools"
      />,
    );

    fireEvent.click(screen.getByRole("button"));

    expect(onToggle).toHaveBeenCalledTimes(1);
  });
});

describe("readCatalogPreference", () => {
  it("accepts every server-supported value", () => {
    expect(readCatalogPreference("saved")).toBe("saved");
    expect(readCatalogPreference("dismissed")).toBe("dismissed");
    expect(readCatalogPreference("none")).toBe("none");
    expect(readCatalogPreference("all")).toBe("all");
  });

  it("falls back to all for an absent or tampered parameter", () => {
    expect(readCatalogPreference(null)).toBe("all");
    expect(readCatalogPreference("")).toBe("all");
    expect(readCatalogPreference("SAVED")).toBe("all");
    expect(readCatalogPreference("saved; drop table")).toBe("all");
    expect(isCatalogPreference("favourites")).toBe(false);
  });
});
