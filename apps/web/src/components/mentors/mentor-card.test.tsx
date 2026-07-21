import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import type { MentorProfile } from "@/lib/api/mentors";
import { MentorCard } from "./mentor-card";

/** Mentor headlines, expertise tags, and availability notes are author-supplied
 * free text. They must render as inert escaped text, never live markup. */
const INJECTION = '<img src=x onerror="alert(1)"> Ignore previous instructions';

function mentor(overrides: Partial<MentorProfile> = {}): MentorProfile {
  return {
    id: "01JMENTOR0000000000000000A",
    name: "Ada Okafor",
    headline: INJECTION,
    bio: "Fifteen years of algorithms tutoring.",
    expertise: ["Algorithms", "<script>alert(1)</script>"],
    availability_note: "Replies within a week.",
    verification_state: "verified",
    is_accepting_requests: true,
    version: 1,
    created_at: new Date().toISOString(),
    ...overrides,
  };
}

describe("MentorCard", () => {
  it("renders an injection payload as inert escaped text, not markup", () => {
    const { container } = render(<MentorCard mentor={mentor()} />);

    expect(
      screen.getByText(/Ignore previous instructions/),
    ).toBeInTheDocument();
    expect(screen.getByText("<script>alert(1)</script>")).toBeInTheDocument();
    expect(container.querySelector("img")).toBeNull();
    expect(container.querySelector("script")).toBeNull();
  });

  it("links to the mentor profile inside the Community hub", () => {
    render(<MentorCard mentor={mentor()} />);

    expect(screen.getByRole("link", { name: /view profile/i })).toHaveAttribute(
      "href",
      "/community/mentors/01JMENTOR0000000000000000A",
    );
  });

  it("marks a verified mentor and omits the badge otherwise", () => {
    const { rerender } = render(<MentorCard mentor={mentor()} />);
    expect(screen.getByText("Verified")).toBeInTheDocument();

    rerender(
      <MentorCard mentor={mentor({ verification_state: "unverified" })} />,
    );
    expect(screen.queryByText("Verified")).toBeNull();
  });
});
