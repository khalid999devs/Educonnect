import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it, vi } from "vitest";

import { Button } from "./button";
import { Dialog } from "./dialog";

function ControlledDialog({ onClose }: { onClose: () => void }) {
  return (
    <Dialog
      open
      onClose={onClose}
      title="Delete task"
      description="This cannot be undone."
      footer={<Button>Confirm</Button>}
    >
      <p>Body content</p>
    </Dialog>
  );
}

describe("Dialog", () => {
  it("renders labelled modal semantics", () => {
    render(<ControlledDialog onClose={vi.fn()} />);

    const dialog = screen.getByRole("dialog", { name: "Delete task" });

    expect(dialog).toHaveAttribute("aria-modal", "true");
    expect(dialog).toHaveAccessibleDescription("This cannot be undone.");
  });

  it("renders nothing while closed", () => {
    render(
      <Dialog open={false} onClose={vi.fn()} title="Hidden">
        <p>Never visible</p>
      </Dialog>,
    );

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("closes on Escape", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    render(<ControlledDialog onClose={onClose} />);

    await user.keyboard("{Escape}");

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("moves initial focus inside and traps Tab at the edges", async () => {
    const user = userEvent.setup();

    render(<ControlledDialog onClose={vi.fn()} />);

    const close = screen.getByRole("button", { name: "Close dialog" });
    const confirm = screen.getByRole("button", { name: "Confirm" });

    expect(close).toHaveFocus();

    await user.tab();
    expect(confirm).toHaveFocus();

    await user.tab();
    expect(close).toHaveFocus();

    await user.tab({ shift: true });
    expect(confirm).toHaveFocus();
  });

  it("restores focus to the opener when closed", async () => {
    const user = userEvent.setup();

    function Harness() {
      const [open, setOpen] = useState(false);

      return (
        <>
          <Button onClick={() => setOpen(true)}>Open</Button>
          <Dialog open={open} onClose={() => setOpen(false)} title="Focus">
            <p>Body</p>
          </Dialog>
        </>
      );
    }

    render(<Harness />);

    const opener = screen.getByRole("button", { name: "Open" });

    await user.click(opener);
    expect(screen.getByRole("dialog")).toBeInTheDocument();

    await user.keyboard("{Escape}");

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(opener).toHaveFocus();
  });
});
