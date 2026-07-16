"use client";

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CopilotTrigger,
} from "@educonnect/ui";
import { useEffect, useState } from "react";

/**
 * The single Copilot mount for the student shell. The trigger is real and
 * coordinated here; the assistant itself arrives in a later phase, and the
 * panel says exactly that.
 */
export function AppCopilot() {
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsOpen(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);

    return () => window.removeEventListener("keydown", onKeyDown);
  }, [isOpen]);

  return (
    <>
      {isOpen ? (
        <section
          aria-label="EduConnect Copilot"
          className="fixed right-6 z-50 w-80 max-w-[calc(100vw-3rem)] bottom-[calc(9.5rem+env(safe-area-inset-bottom))] md:bottom-24"
        >
          <Card className="border-status-ai/30 shadow-glow-sm">
            <CardHeader>
              <CardTitle as="h2">Copilot</CardTitle>
            </CardHeader>
            <CardContent>
              The Copilot assistant arrives in a later phase. This shell
              reserves its single floating trigger; no page renders a second
              one.
            </CardContent>
          </Card>
        </section>
      ) : null}
      <CopilotTrigger
        isOpen={isOpen}
        onToggle={() => setIsOpen((open) => !open)}
        className="bottom-[calc(4.75rem+env(safe-area-inset-bottom))] md:bottom-[max(1.5rem,env(safe-area-inset-bottom))]"
      />
    </>
  );
}
