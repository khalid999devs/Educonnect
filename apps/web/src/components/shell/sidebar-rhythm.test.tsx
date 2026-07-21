import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { ActivityRhythmDay, Progress } from "@/lib/api/progress";
import { SidebarRhythm, SidebarRhythmStrip } from "./sidebar-rhythm";

const getProgress = vi.fn();

vi.mock("@/lib/api/progress", () => ({
  getProgress: (params: unknown) => getProgress(params),
}));

/**
 * PRODUCT INVARIANT under test (README.md:36, ADR-0018, and 13 other places):
 * the sidebar shows real recorded days only. No consecutive-day counter, no
 * best run, no flame or badge, no percentile, no "vs last week" delta. This
 * suite exists so the invariant cannot be quietly regressed into the one
 * surface that renders on every authenticated page.
 */
const VANITY_PATTERNS = [
  /streak/i,
  /consecutive/i,
  /\bin a row\b/i,
  /day run\b/i,
  /best ever/i,
  /personal best/i,
  /percentile/i,
  /top \d+%/i,
  /vs\.? last (week|month|term)/i,
  /compared to last/i,
  /\bbadge\b/i,
  /\btrophy\b/i,
  /\bflame\b/i,
  /keep it up/i,
  /you'?re on fire/i,
  /level \d/i,
  /\bxp\b/i,
];

function day(overrides: Partial<ActivityRhythmDay> = {}): ActivityRhythmDay {
  return {
    date: "2026-07-20",
    was_active: false,
    signals: { tasks: 0, focus_minutes: 0, resources: 0, notes: 0 },
    ...overrides,
  };
}

const WEEK: ActivityRhythmDay[] = [
  day({
    date: "2026-07-20",
    was_active: true,
    signals: { tasks: 2, focus_minutes: 45, resources: 0, notes: 0 },
  }),
  day({
    date: "2026-07-21",
    was_active: true,
    signals: { tasks: 0, focus_minutes: 0, resources: 1, notes: 3 },
  }),
  day({ date: "2026-07-22" }),
  day({ date: "2026-07-23" }),
  day({ date: "2026-07-24" }),
  day({ date: "2026-07-25" }),
  day({ date: "2026-07-26" }),
];

const QUIET_WEEK: ActivityRhythmDay[] = WEEK.map((entry) =>
  day({ date: entry.date }),
);

function progressFixture(days: ActivityRhythmDay[]): Progress {
  return {
    timeframe: {
      timezone: "Asia/Dhaka",
      window: "week",
      starts_on: "2026-07-20",
      ends_on: "2026-07-26",
      term_label: null,
    },
    has_activity: days.some((entry) => entry.was_active),
    summary: "2 tasks completed this week.",
    totals: {
      tasks_completed: 2,
      tasks_due: 4,
      focus_minutes: 45,
      resources_added: 1,
      intake_items_processed: 0,
      knowledge_items_added: 0,
      notes_written: 3,
      template_copies_created: 0,
      research_sources_reviewed: 0,
    },
    daily: [],
    activity_rhythm: {
      has_activity: days.some((entry) => entry.was_active),
      days,
    },
    next_action: null,
  };
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe("SidebarRhythmStrip", () => {
  it("labels every day with what actually happened on it", () => {
    render(<SidebarRhythmStrip days={WEEK} />);

    expect(
      screen.getByText("Mon 20 Jul: 2 tasks, 45 min focus"),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Tue 21 Jul: 1 resource, 3 notes"),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Wed 22 Jul: no activity recorded"),
    ).toBeInTheDocument();
  });

  it.each([
    ["an active week", WEEK],
    ["a quiet week", QUIET_WEEK],
  ])("renders no streak or vanity affordance for %s", (_label, days) => {
    const { container } = render(<SidebarRhythmStrip days={days} />);
    const text = container.textContent ?? "";

    for (const pattern of VANITY_PATTERNS) {
      expect(text).not.toMatch(pattern);
    }

    expect(container.querySelectorAll("svg")).toHaveLength(0);
  });
});

describe("SidebarRhythm", () => {
  beforeEach(() => {
    getProgress.mockReset();
  });

  it("reads the real week window in the browser timezone", async () => {
    getProgress.mockResolvedValue(progressFixture(WEEK));

    render(<SidebarRhythm />, { wrapper });

    await waitFor(() => {
      expect(getProgress).toHaveBeenCalledWith({
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        window: "week",
      });
    });

    expect(
      await screen.findByText("Mon 20 Jul: 2 tasks, 45 min focus"),
    ).toBeInTheDocument();
  });

  it("renders nothing when the read fails, so navigation is never broken", async () => {
    getProgress.mockRejectedValue(new Error("network down"));

    const { container } = render(<SidebarRhythm />, { wrapper });

    await waitFor(() => {
      expect(getProgress).toHaveBeenCalled();
    });

    await waitFor(() => {
      expect(container.textContent).toBe("");
    });
  });
});
