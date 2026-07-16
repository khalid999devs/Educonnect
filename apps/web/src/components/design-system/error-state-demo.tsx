"use client";

import { ErrorState } from "@educonnect/ui";
import { useState } from "react";

export function ErrorStateDemo() {
  const [retries, setRetries] = useState(0);

  return (
    <div className="space-y-2">
      <ErrorState
        title="Could not load this section"
        description="A network problem interrupted the request."
        onRetry={() => setRetries((count) => count + 1)}
      />
      <p
        aria-live="polite"
        className="text-caption tabular-nums text-text-muted"
      >
        Retry pressed {retries} {retries === 1 ? "time" : "times"} in this demo.
      </p>
    </div>
  );
}
