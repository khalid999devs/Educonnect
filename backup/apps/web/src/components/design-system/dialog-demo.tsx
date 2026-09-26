"use client";

import { Button, Dialog } from "@educonnect/ui";
import { useState } from "react";

/**
 * Gallery-only driver for the modal Dialog. Demonstrates the labelled
 * dialog, focus trap with restore, Escape/backdrop close, and a footer.
 */
export function DialogDemo() {
  const [open, setOpen] = useState(false);

  return (
    <div>
      <Button onClick={() => setOpen(true)}>Open dialog</Button>
      <Dialog
        open={open}
        onClose={() => setOpen(false)}
        title="Archive this task?"
        description="It moves out of your plan but stays recoverable."
        footer={
          <>
            <Button variant="ghost" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={() => setOpen(false)}>
              Archive task
            </Button>
          </>
        }
      >
        <p className="text-body text-text-secondary">
          Focus moves to the panel while it is open; Escape or the backdrop
          closes it and returns focus to the trigger.
        </p>
      </Dialog>
    </div>
  );
}
