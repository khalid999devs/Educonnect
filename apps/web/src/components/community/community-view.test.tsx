import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

const replace = vi.fn();
let searchParams = new URLSearchParams();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
  usePathname: () => "/community",
  useSearchParams: () => searchParams,
}));

vi.mock("./posts-tab", () => ({ PostsTab: () => <p>posts panel</p> }));
vi.mock("./groups-tab", () => ({ GroupsTab: () => <p>groups panel</p> }));
vi.mock("./people-tab", () => ({ PeopleTab: () => <p>people panel</p> }));
vi.mock("@/components/mentors/mentors-tab", () => ({
  MentorsTab: () => <p>mentors panel</p>,
}));

const { CommunityView } = await import("./community-view");

describe("CommunityView tabs", () => {
  beforeEach(() => {
    replace.mockClear();
    searchParams = new URLSearchParams();
  });

  it("shows Posts by default and marks it selected", () => {
    render(<CommunityView />);

    expect(screen.getByText("posts panel")).toBeInTheDocument();
    expect(screen.getByRole("tab", { name: /posts/i })).toHaveAttribute(
      "aria-selected",
      "true",
    );
  });

  it("restores the tab named by the URL", () => {
    searchParams = new URLSearchParams("tab=groups");
    render(<CommunityView />);

    expect(screen.getByText("groups panel")).toBeInTheDocument();
    expect(screen.getByRole("tab", { name: /groups/i })).toHaveAttribute(
      "aria-selected",
      "true",
    );
  });

  it("falls back to Posts for an unknown tab value", () => {
    searchParams = new URLSearchParams("tab=nonsense");
    render(<CommunityView />);

    expect(screen.getByText("posts panel")).toBeInTheDocument();
  });

  it("writes the chosen tab into the URL, and drops the param for Posts", async () => {
    const user = userEvent.setup();
    render(<CommunityView />);

    await user.click(screen.getByRole("tab", { name: /people/i }));
    expect(replace).toHaveBeenCalledWith("/community?tab=people", {
      scroll: false,
    });

    searchParams = new URLSearchParams("tab=people");
    replace.mockClear();
    render(<CommunityView />);

    await user.click(screen.getAllByRole("tab", { name: /posts/i })[1]!);
    expect(replace).toHaveBeenCalledWith("/community", { scroll: false });
  });

  it("preserves unrelated search params when switching tabs", async () => {
    const user = userEvent.setup();
    searchParams = new URLSearchParams("ref=email");
    render(<CommunityView />);

    await user.click(screen.getByRole("tab", { name: /mentors/i }));
    expect(replace).toHaveBeenCalledWith("/community?ref=email&tab=mentors", {
      scroll: false,
    });
  });

  it("moves between tabs with arrow keys, Home, and End", async () => {
    const user = userEvent.setup();
    render(<CommunityView />);

    const posts = screen.getByRole("tab", { name: /posts/i });
    posts.focus();

    await user.keyboard("{ArrowRight}");
    expect(replace).toHaveBeenLastCalledWith("/community?tab=mentors", {
      scroll: false,
    });

    await user.keyboard("{End}");
    expect(replace).toHaveBeenLastCalledWith("/community?tab=people", {
      scroll: false,
    });

    await user.keyboard("{Home}");
    expect(replace).toHaveBeenLastCalledWith("/community", { scroll: false });

    await user.keyboard("{ArrowLeft}");
    expect(replace).toHaveBeenLastCalledWith("/community?tab=people", {
      scroll: false,
    });
  });

  it("ties every tab to its panel for assistive technology", () => {
    render(<CommunityView />);

    const panel = screen.getByRole("tabpanel");
    expect(panel).toHaveAttribute("id", "community-panel-posts");
    expect(panel).toHaveAttribute("aria-labelledby", "section-tab-posts");
    expect(screen.getByRole("tab", { name: /posts/i })).toHaveAttribute(
      "aria-controls",
      "community-panel-posts",
    );
  });
});
