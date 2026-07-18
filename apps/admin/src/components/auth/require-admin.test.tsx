import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { useSession } from "@/providers/session-provider";

import { RequireAdmin } from "./require-admin";

const replace = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
  usePathname: () => "/users",
}));

vi.mock("@/providers/session-provider", () => ({
  useSession: vi.fn(),
}));

const mockedUseSession = vi.mocked(useSession);

function session(status: "loading" | "authenticated" | "guest") {
  return {
    status,
    session: null,
    setSession: vi.fn(),
    refresh: vi.fn(),
    can: vi.fn(() => false),
  };
}

afterEach(() => {
  vi.clearAllMocks();
});

describe("RequireAdmin", () => {
  it("redirects a guest to the sign-in page with a next hint", async () => {
    mockedUseSession.mockReturnValue(session("guest"));

    render(
      <RequireAdmin>
        <p>console content</p>
      </RequireAdmin>,
    );

    await waitFor(() =>
      expect(replace).toHaveBeenCalledWith("/login?next=%2Fusers"),
    );
    expect(screen.queryByText("console content")).not.toBeInTheDocument();
  });

  it("shows a verifying state while the session loads and does not redirect", () => {
    mockedUseSession.mockReturnValue(session("loading"));

    render(
      <RequireAdmin>
        <p>console content</p>
      </RequireAdmin>,
    );

    expect(screen.queryByText("console content")).not.toBeInTheDocument();
    expect(replace).not.toHaveBeenCalled();
  });

  it("renders the console for an authenticated admin", () => {
    mockedUseSession.mockReturnValue(session("authenticated"));

    render(
      <RequireAdmin>
        <p>console content</p>
      </RequireAdmin>,
    );

    expect(screen.getByText("console content")).toBeInTheDocument();
    expect(replace).not.toHaveBeenCalled();
  });
});
