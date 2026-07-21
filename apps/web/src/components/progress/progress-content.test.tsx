import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import type { Progress } from "@/lib/api/progress";
import { buildMilestones } from "./milestone-feed";
import { ProgressContent } from "./progress-content";
import { RhythmStrip } from "./rhythm-strip";

/**
 * PRODUCT INVARIANT under test (README.md, ADR-0018, and 13 other places):
 * Progress renders real counts only. No consecutive-day counter, no best
 * streak, no flame or badge, no percentile, no "vs last week" delta. The
 * backend refuses to compute these; this suite proves the presentation layer
 * does not reintroduce them.
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

function progressFixture(overrides: Partial<Progress> = {}): Progress {
  return {
    timeframe: {
      timezone: "Asia/Dhaka",
      window: "week",
      starts_on: "2026-07-20",
      ends_on: "2026-07-27",
      term_label: null,
    },
    has_activity: true,
    summary: "3 tasks completed, 90 focus minutes logged this week.",
    totals: {
      tasks_completed: 3,
      tasks_due: 5,
      focus_minutes: 90,
      resources_added: 2,
      intake_items_processed: 1,
      knowledge_items_added: 4,
      notes_written: 6,
      template_copies_created: 1,
      research_sources_reviewed: 2,
    },
    daily: [
      {
        date: "2026-07-20",
        tasks_completed: 1,
        focus_minutes: 25,
        resources_added: 1,
        notes_written: 2,
      },
      {
        date: "2026-07-21",
        tasks_completed: 2,
        focus_minutes: 65,
        resources_added: 1,
        notes_written: 4,
      },
    ],
    activity_rhythm: {
      has_activity: true,
      days: [
        "2026-07-15",
        "2026-07-16",
        "2026-07-17",
        "2026-07-18",
        "2026-07-19",
        "2026-07-20",
        "2026-07-21",
      ].map((date, index) => ({
        date,
        was_active: index % 2 === 0,
        signals: {
          tasks: index % 2 === 0 ? 1 : 0,
          focus_minutes: index % 2 === 0 ? 25 : 0,
          resources: 0,
          notes: 0,
        },
      })),
    },
    next_action: {
      kind: "task",
      id: "01JTASK0000000000000000000",
      title: "Problem set 2",
      due_at: "2026-07-22T18:00:00Z",
    },
    ...overrides,
  };
}

function renderContent(progress: Progress) {
  return render(
    <ProgressContent
      progress={progress}
      metric="tasks_completed"
      onMetricChange={vi.fn()}
    />,
  );
}

describe("ProgressContent product invariant", () => {
  it("renders no streak, badge, percentile, or period-comparison language", () => {
    const { container } = renderContent(progressFixture());
    const text = container.textContent ?? "";

    for (const pattern of VANITY_PATTERNS) {
      expect(text, `matched forbidden pattern ${pattern}`).not.toMatch(pattern);
    }
  });

  it("renders no vanity language on an all-zero window either", () => {
    const empty = progressFixture({
      has_activity: false,
      summary: "Nothing recorded this week.",
      totals: {
        tasks_completed: 0,
        tasks_due: 0,
        focus_minutes: 0,
        resources_added: 0,
        intake_items_processed: 0,
        knowledge_items_added: 0,
        notes_written: 0,
        template_copies_created: 0,
        research_sources_reviewed: 0,
      },
      daily: [
        {
          date: "2026-07-20",
          tasks_completed: 0,
          focus_minutes: 0,
          resources_added: 0,
          notes_written: 0,
        },
      ],
      next_action: null,
    });

    const { container } = renderContent(empty);
    const text = container.textContent ?? "";

    for (const pattern of VANITY_PATTERNS) {
      expect(text, `matched forbidden pattern ${pattern}`).not.toMatch(pattern);
    }
  });

  it("says plainly that an empty window is empty rather than dressing up zero", () => {
    const empty = progressFixture({
      has_activity: false,
      summary: "Nothing recorded this week.",
      totals: {
        tasks_completed: 0,
        tasks_due: 0,
        focus_minutes: 0,
        resources_added: 0,
        intake_items_processed: 0,
        knowledge_items_added: 0,
        notes_written: 0,
        template_copies_created: 0,
        research_sources_reviewed: 0,
      },
      daily: [
        {
          date: "2026-07-20",
          tasks_completed: 0,
          focus_minutes: 0,
          resources_added: 0,
          notes_written: 0,
        },
      ],
      next_action: null,
    });

    renderContent(empty);

    expect(screen.getByText(/Nothing was recorded this week/)).toBeVisible();
    expect(screen.getByText(/No tasks were due this week/)).toBeVisible();
    expect(
      screen.getByText(/Nothing recorded in this window yet/),
    ).toBeVisible();
  });

  it("shows real totals from the payload", () => {
    renderContent(progressFixture());

    expect(screen.getByText("3 / 5")).toBeInTheDocument();
    expect(screen.getByText("1 h 30 min")).toBeInTheDocument();
    expect(
      screen.getByText("3 tasks completed, 90 focus minutes logged this week."),
    ).toBeInTheDocument();
  });

  it("gives the chart a data-bearing accessible label", () => {
    renderContent(progressFixture());

    const chart = screen.getByRole("img", { name: /Tasks completed by day/ });

    expect(chart.getAttribute("aria-label")).toContain("20 Jul: 1");
    expect(chart.getAttribute("aria-label")).toContain("21 Jul: 2");
  });

  it("reports the completion ring as a real fraction of tasks due", () => {
    renderContent(progressFixture());

    expect(
      screen.getByRole("img", {
        name: /60 percent of the 5 tasks due this week are completed/,
      }),
    ).toBeInTheDocument();
  });
});

describe("buildMilestones", () => {
  it("derives entries only from days that actually have signals", () => {
    const milestones = buildMilestones([
      {
        date: "2026-07-20",
        tasks_completed: 0,
        focus_minutes: 0,
        resources_added: 0,
        notes_written: 0,
      },
      {
        date: "2026-07-21",
        tasks_completed: 2,
        focus_minutes: 0,
        resources_added: 0,
        notes_written: 1,
      },
    ]);

    expect(milestones.map((milestone) => milestone.title)).toEqual([
      "2 tasks completed",
      "1 note written",
    ]);
  });

  it("returns nothing for an empty window rather than inventing filler", () => {
    expect(
      buildMilestones([
        {
          date: "2026-07-20",
          tasks_completed: 0,
          focus_minutes: 0,
          resources_added: 0,
          notes_written: 0,
        },
      ]),
    ).toEqual([]);
  });
});

describe("RhythmStrip", () => {
  it("describes quiet days without any streak framing", () => {
    const days = [
      "2026-07-15",
      "2026-07-16",
      "2026-07-17",
      "2026-07-18",
      "2026-07-19",
      "2026-07-20",
      "2026-07-21",
    ].map((date) => ({
      date,
      was_active: false,
      signals: { tasks: 0, focus_minutes: 0, resources: 0, notes: 0 },
    }));

    const { container } = render(<RhythmStrip days={days} />);
    const text = container.textContent ?? "";

    expect(text).toMatch(/No recorded activity in the last seven days/);

    for (const pattern of VANITY_PATTERNS) {
      expect(text, `matched forbidden pattern ${pattern}`).not.toMatch(pattern);
    }
  });
});
