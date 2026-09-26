import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { CopilotTrigger } from "./copilot-trigger";

describe("CopilotTrigger", () => {
  it("is collapsed by default with an accessible open affordance", () => {
    render(<CopilotTrigger />);

    const trigger = screen.getByRole("button", {
      name: "Open EduConnect Copilot",
    });

    expect(trigger).toHaveAttribute("aria-expanded", "false");
    expect(trigger).toHaveAttribute("aria-haspopup", "dialog");
  });

  it("reflects the open state and notifies the shell on toggle", async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();

    render(<CopilotTrigger isOpen onToggle={onToggle} />);

    const trigger = screen.getByRole("button", {
      name: "Close EduConnect Copilot",
    });

    expect(trigger).toHaveAttribute("aria-expanded", "true");

    await user.click(trigger);

    expect(onToggle).toHaveBeenCalledTimes(1);
  });
});
