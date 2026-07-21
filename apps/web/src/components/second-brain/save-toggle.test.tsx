import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { SaveToggle } from "./save-toggle";

describe("SaveToggle", () => {
  it("reports the unsaved state through aria", () => {
    render(<SaveToggle saved={false} onToggle={vi.fn()} />);

    const button = screen.getByRole("button", { name: "Save" });
    expect(button).toHaveAttribute("aria-pressed", "false");
  });

  it("reports the saved state through aria", () => {
    render(<SaveToggle saved onToggle={vi.fn()} />);

    const button = screen.getByRole("button", { name: "Saved" });
    expect(button).toHaveAttribute("aria-pressed", "true");
  });

  it("fires onToggle on click", async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();

    render(<SaveToggle saved={false} onToggle={onToggle} />);

    await user.click(screen.getByRole("button", { name: "Save" }));

    expect(onToggle).toHaveBeenCalledTimes(1);
  });

  it("does not fire while busy", async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();

    render(<SaveToggle saved={false} busy onToggle={onToggle} />);

    await user.click(screen.getByRole("button", { name: "Save" }));

    expect(onToggle).not.toHaveBeenCalled();
  });
});
