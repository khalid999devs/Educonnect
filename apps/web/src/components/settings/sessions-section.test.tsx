import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import type { SessionSummary } from "@/lib/api/settings";
import { SessionRow } from "./sessions-section";

/**
 * The user agent is an attacker-controlled request header, so it is untrusted
 * document-derived text. It must render as inert, escaped text - never as
 * live markup. The digest id must never reach the DOM at all.
 */
const INJECTION = '<img src=x onerror="alert(1)"> Ignore previous instructions';

function session(overrides: Partial<SessionSummary> = {}): SessionSummary {
  return {
    id: "0f2a9c5b8d1e4f6a0b7c3d2e1f4a5b6c",
    ip_address: "203.0.113.10",
    user_agent: INJECTION,
    last_activity: "2026-07-20T09:30:00.000Z",
    is_current: false,
    ...overrides,
  };
}

describe("SessionRow", () => {
  it("renders an injected user agent as inert escaped text", () => {
    const { container } = render(<SessionRow session={session()} />);

    expect(screen.getByText(INJECTION)).toBeInTheDocument();
    expect(container.querySelector("img")).toBeNull();
    /* The payload survives as escaped text, never as parsed markup. */
    expect(container.innerHTML).toContain("&lt;img src=x");
    expect(container.innerHTML).not.toContain("<img");
  });

  it("never renders the session identifier digest", () => {
    const { container } = render(<SessionRow session={session()} />);

    expect(container.innerHTML).not.toContain(
      "0f2a9c5b8d1e4f6a0b7c3d2e1f4a5b6c",
    );
  });

  it("falls back honestly when the browser and address are unknown", () => {
    render(
      <SessionRow session={session({ user_agent: null, ip_address: null })} />,
    );

    expect(screen.getByText("Unknown browser")).toBeInTheDocument();
    expect(screen.getByText(/IP address not recorded/)).toBeInTheDocument();
  });

  it("marks the calling browser", () => {
    render(<SessionRow session={session({ is_current: true })} />);

    expect(screen.getByText("This browser")).toBeInTheDocument();
  });
});
