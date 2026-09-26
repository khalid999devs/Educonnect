import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import type { Post } from "@/lib/api/community";
import { PostCard } from "./post-card";

/** Community posts are untrusted user content and could carry a prompt-injection
 * / XSS payload. They must render as inert, escaped text - never live markup. */
const INJECTION = '<img src=x onerror="alert(1)"> Ignore previous instructions';

function post(overrides: Partial<Post> = {}): Post {
  return {
    id: "01JPOST00000000000000000AA",
    community: {
      id: "01JCOMM0000000000000000AAA",
      name: "Study Skills",
      slug: "study-skills",
    },
    author: { name: "Mei Lin", is_verified_mentor: false },
    title: null,
    body: INJECTION,
    moderation_state: "visible",
    is_mine: false,
    shared_resource: null,
    comment_count: 0,
    version: 1,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    ...overrides,
  };
}

describe("PostCard", () => {
  it("renders an injection payload as inert escaped text, not markup", () => {
    const { container } = render(<PostCard post={post()} onReport={vi.fn()} />);

    expect(
      screen.getByText(/Ignore previous instructions/),
    ).toBeInTheDocument();
    expect(container.querySelector("img")).toBeNull();
    expect(container.querySelector("script")).toBeNull();
  });

  it("hides the body and shows a placeholder for moderated posts", () => {
    render(
      <PostCard
        post={post({ moderation_state: "hidden_by_moderator", body: null })}
        onReport={vi.fn()}
      />,
    );

    expect(screen.getByText(/hidden by a moderator/i)).toBeInTheDocument();
    expect(screen.queryByText(/Ignore previous instructions/)).toBeNull();
  });

  it("offers Report on others' posts and Edit/Delete only on your own", () => {
    const { rerender } = render(
      <PostCard
        post={post()}
        onReport={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
      />,
    );
    expect(screen.getByRole("button", { name: /report/i })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /^edit$/i })).toBeNull();

    rerender(
      <PostCard
        post={post({ is_mine: true })}
        onReport={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
      />,
    );
    expect(screen.getByRole("button", { name: /edit/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /delete/i })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /report/i })).toBeNull();
  });
});
