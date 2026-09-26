import { render, screen } from "@testing-library/react";
import { Brain } from "lucide-react";
import { describe, expect, it } from "vitest";

import { SECTION_ACCENT } from "@/components/shell/section-accent";
import { IconChip } from "./icon-chip";

describe("IconChip", () => {
  it("applies the section accent fill and glyph color as literal classes", () => {
    render(<IconChip icon={Brain} accent="secondBrain" />);

    const chip = screen.getByTestId("icon-chip");

    expect(chip.className).toContain("bg-status-research/12");
    expect(chip.querySelector("svg")?.getAttribute("class")).toContain(
      "text-status-research",
    );
  });

  it("marks the glyph decorative so it is skipped by assistive technology", () => {
    render(<IconChip icon={Brain} accent="study" />);

    const glyph = screen.getByTestId("icon-chip").querySelector("svg");

    expect(glyph?.getAttribute("aria-hidden")).toBe("true");
  });

  it("uses rounded-md for inline sizes and rounded-xl for hero sizes", () => {
    const { rerender } = render(<IconChip icon={Brain} accent="home" />);

    expect(screen.getByTestId("icon-chip").className).toContain(
      "size-9 rounded-md",
    );

    rerender(<IconChip icon={Brain} accent="home" size="hero" />);

    expect(screen.getByTestId("icon-chip").className).toContain(
      "size-13 rounded-xl",
    );
  });

  it("renders the accent border only when bordered is set", () => {
    const { rerender } = render(<IconChip icon={Brain} accent="community" />);

    expect(screen.getByTestId("icon-chip").className).not.toContain(
      "border-status-success/30",
    );

    rerender(<IconChip icon={Brain} accent="community" bordered />);

    expect(screen.getByTestId("icon-chip").className).toContain(
      "border-status-success/30",
    );
  });

  it("accepts an inline accent object as well as a section key", () => {
    render(<IconChip icon={Brain} accent={SECTION_ACCENT.planner} />);

    expect(screen.getByTestId("icon-chip").className).toContain(
      "bg-status-deadline/12",
    );
  });
});

describe("SECTION_ACCENT", () => {
  /* Tailwind v4 scans source text: a template-literal class never reaches the
   * generated stylesheet, so every value must be a complete literal. */
  it("never uses status-error or status-warning as decoration", () => {
    const serialized = JSON.stringify(SECTION_ACCENT);

    expect(serialized).not.toContain("status-error");
    expect(serialized).not.toContain("status-warning");
  });

  it("keeps every chip fill at or below the /20 alpha ceiling", () => {
    for (const accent of Object.values(SECTION_ACCENT)) {
      const alpha = accent.chip.match(/\/(\d+)$/);

      if (alpha) {
        expect(Number(alpha[1])).toBeLessThanOrEqual(20);
      }
    }
  });
});
