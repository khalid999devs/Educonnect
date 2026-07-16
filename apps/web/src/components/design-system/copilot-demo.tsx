"use client";

import { CopilotTrigger } from "@educonnect/ui";
import { useState } from "react";

/** Inline (non-floating) trigger demo; the shells own the single real one. */
export function CopilotDemo() {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="flex items-center gap-4">
      <CopilotTrigger
        isOpen={isOpen}
        onToggle={() => setIsOpen((open) => !open)}
        className="static"
      />
      <p className="text-body text-text-secondary">
        {isOpen ? "Open state (close icon shown)." : "Collapsed by default."}
      </p>
    </div>
  );
}
