import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type * as httpModule from "@/lib/api/http";
import type { MentorRequestStatus } from "@/lib/api/mentors";
import type { SessionStatus } from "@/providers/session-provider";
import {
  STUDENT_MOBILE_NAV,
  isStudentNavItemActive,
  useStudentNav,
  type StudentNavItem,
} from "./student-nav";

/**
 * The gate is mocked at the one HTTP boundary rather than by stubbing
 * `hasAcceptedMentor`, so these tests exercise the real status filter and the
 * real meta reading. Stubbing the helper would have proved only that the mock
 * returns what it was told to.
 */
const apiFetch = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api/http", async (importOriginal) => ({
  ...(await importOriginal<typeof httpModule>()),
  apiFetch,
}));

let sessionStatus: SessionStatus = "authenticated";

vi.mock("@/providers/session-provider", () => ({
  useSession: () => ({
    status: sessionStatus,
    user: null,
    setUser: vi.fn(),
    refresh: vi.fn(),
  }),
}));

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

function labels(items: StudentNavItem[]): string[] {
  return items.map((item) => item.label);
}

function requestRow(status: MentorRequestStatus) {
  return {
    id: "01JMENTORREQUEST0000000AB",
    subject: "Thesis direction",
    message: "Could you look over my outline?",
    status,
    response_note: null,
    version: 1,
    created_at: "2026-07-01T09:00:00.000Z",
    responded_at: null,
  };
}

/**
 * Stands in for `GET /mentor-requests`: filters the student's requests by the
 * `status` values the caller actually asked for, exactly as the API does, and
 * reports the filtered total in `meta.summary`.
 */
function serveRequests(all: MentorRequestStatus[]) {
  apiFetch.mockImplementation(async (path: string) => {
    const asked =
      new URL(path, "https://test.local").searchParams
        .get("status")
        ?.split(",") ?? [];
    const matching = all.filter((status) => asked.includes(status));

    return {
      data: matching.slice(0, 1).map(requestRow),
      meta: {
        summary: { total: matching.length },
        pagination: {
          next_cursor: null,
          previous_cursor: null,
          per_page: 1,
        },
      },
    };
  });
}

const BASE_LABELS = [
  "Home",
  "Second Brain",
  "Study",
  "Planner",
  "Resources",
  "AI Tools",
  "Templates",
  "Community",
  "Progress",
  "Settings",
];

describe("useStudentNav", () => {
  beforeEach(() => {
    sessionStatus = "authenticated";
    apiFetch.mockReset();
  });

  it("renders exactly the ten core destinations", async () => {
    serveRequests([]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    await waitFor(() => expect(apiFetch).toHaveBeenCalled());

    expect(labels(result.current)).toEqual(BASE_LABELS);
  });

  it("hides Mentors when the student has no mentor connection", async () => {
    /* An open request and a declined one are not connections. */
    serveRequests(["open", "declined", "withdrawn"]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    await waitFor(() => expect(apiFetch).toHaveBeenCalled());

    expect(labels(result.current)).not.toContain("Mentors");
    expect(labels(result.current)).toHaveLength(10);
  });

  it("shows Mentors for an accepted connection", async () => {
    serveRequests(["accepted"]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    await waitFor(() => expect(labels(result.current)).toContain("Mentors"));
  });

  it("shows Mentors for a completed connection", async () => {
    /* Finishing a mentorship must not take the student's history away. */
    serveRequests(["completed"]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    await waitFor(() => expect(labels(result.current)).toContain("Mentors"));
  });

  it("does not flicker while the gate is loading", async () => {
    let release: (() => void) | undefined;
    const pending = new Promise<void>((resolve) => {
      release = resolve;
    });

    apiFetch.mockImplementation(async () => {
      await pending;

      return {
        data: [requestRow("accepted")],
        meta: {
          summary: { total: 1 },
          pagination: {
            next_cursor: null,
            previous_cursor: null,
            per_page: 1,
          },
        },
      };
    });

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    /* In flight the nav is the plain ten: nothing is shown and then taken
     * away, and there is no placeholder or skeleton row standing in. */
    expect(labels(result.current)).toEqual(BASE_LABELS);

    release?.();

    await waitFor(() => expect(labels(result.current)).toContain("Mentors"));

    /* Mentors arrived by pure addition: the ten kept their order and count. */
    expect(labels(result.current).filter((l) => l !== "Mentors")).toEqual(
      BASE_LABELS,
    );
  });

  it("never queries the gate for a guest", () => {
    sessionStatus = "guest";
    serveRequests(["accepted"]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    expect(apiFetch).not.toHaveBeenCalled();
    expect(labels(result.current)).not.toContain("Mentors");
  });

  it("presents no unavailable destination", async () => {
    serveRequests(["accepted"]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    await waitFor(() => expect(labels(result.current)).toContain("Mentors"));

    for (const item of result.current) {
      expect(item).not.toHaveProperty("available");
      expect(item.href.startsWith("/")).toBe(true);
    }
  });
});

describe("isStudentNavItemActive", () => {
  async function navWithMentors(): Promise<StudentNavItem[]> {
    sessionStatus = "authenticated";
    serveRequests(["accepted"]);

    const { result } = renderHook(() => useStudentNav(), { wrapper });

    await waitFor(() => expect(labels(result.current)).toContain("Mentors"));

    return result.current;
  }

  function pick(items: StudentNavItem[], label: string): StudentNavItem {
    const found = items.find((item) => item.label === label);

    if (found === undefined) {
      throw new Error(`No nav item labelled ${label}`);
    }

    return found;
  }

  beforeEach(() => {
    apiFetch.mockReset();
  });

  it("matches the exact route and its nested routes", async () => {
    const items = await navWithMentors();

    expect(isStudentNavItemActive(pick(items, "Planner"), "/planner")).toBe(
      true,
    );
    expect(
      isStudentNavItemActive(
        pick(items, "Second Brain"),
        "/second-brain/01JITEM/workspace",
      ),
    ).toBe(true);
  });

  it("does not match a route that merely shares a prefix string", async () => {
    const items = await navWithMentors();

    expect(isStudentNavItemActive(pick(items, "Study"), "/study-groups")).toBe(
      false,
    );
  });

  it("keeps Community and Mentors from lighting up at the same time", async () => {
    const items = await navWithMentors();
    const community = pick(items, "Community");
    const mentors = pick(items, "Mentors");

    expect(isStudentNavItemActive(mentors, "/community/mentors/01JM")).toBe(
      true,
    );
    expect(isStudentNavItemActive(community, "/community/mentors/01JM")).toBe(
      false,
    );

    /* Every other community route still belongs to Community. */
    expect(isStudentNavItemActive(community, "/community")).toBe(true);
    expect(isStudentNavItemActive(community, "/community/groups/01JG")).toBe(
      true,
    );
  });
});

describe("STUDENT_MOBILE_NAV", () => {
  it("carries exactly five real destinations", () => {
    expect(STUDENT_MOBILE_NAV.map((item) => item.href)).toEqual([
      "/dashboard",
      "/second-brain",
      "/study",
      "/planner",
      "/resources",
    ]);
  });

  it("no longer points at the removed Smart Intake surface", () => {
    expect(STUDENT_MOBILE_NAV.map((item) => item.href)).not.toContain(
      "/intake",
    );
  });

  it("omits the conditional Mentors entry so the bar never reflows", () => {
    expect(STUDENT_MOBILE_NAV.map((item) => item.label)).not.toContain(
      "Mentors",
    );
  });
});
