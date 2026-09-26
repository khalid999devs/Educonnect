import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { Button, buttonClasses } from "./button";

describe("Button", () => {
  it("renders an accessible button that fires clicks", async () => {
    const user = userEvent.setup();
    const onClick = vi.fn();

    render(<Button onClick={onClick}>Save changes</Button>);

    await user.click(screen.getByRole("button", { name: "Save changes" }));

    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it("defaults to type button so forms are not submitted accidentally", () => {
    render(<Button>Plain</Button>);

    expect(screen.getByRole("button", { name: "Plain" })).toHaveAttribute(
      "type",
      "button",
    );
  });

  it("blocks interaction and announces busy while loading", async () => {
    const user = userEvent.setup();
    const onClick = vi.fn();

    render(
      <Button isLoading loadingLabel="Saving" onClick={onClick}>
        Save changes
      </Button>,
    );

    const button = screen.getByRole("button", { name: /Saving/ });

    expect(button).toBeDisabled();
    expect(button).toHaveAttribute("aria-busy", "true");

    await user.click(button).catch(() => undefined);

    expect(onClick).not.toHaveBeenCalled();
  });

  it("stays disabled when the disabled prop is set", () => {
    render(<Button disabled>Delete</Button>);

    expect(screen.getByRole("button", { name: "Delete" })).toBeDisabled();
  });

  it("applies the documented 44px default and 48px large action heights", () => {
    expect(buttonClasses()).toContain("h-11");
    expect(buttonClasses({ size: "lg" })).toContain("h-12");
  });

  it("reserves glow for the primary variant only", () => {
    expect(buttonClasses({ variant: "primary", glow: true })).toContain(
      "shadow-glow-sm",
    );
    expect(buttonClasses({ variant: "secondary", glow: true })).not.toContain(
      "shadow-glow-sm",
    );
  });
});
