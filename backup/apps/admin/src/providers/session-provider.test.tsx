import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { currentAdmin } from "@/lib/api/admin-auth";
import { ApiError } from "@/lib/api/http";
import type { AdminSession } from "@/lib/api/schemas";

import { SessionProvider, useSession } from "./session-provider";

vi.mock("@/lib/api/admin-auth", () => ({
  currentAdmin: vi.fn(),
}));

const mockedCurrentAdmin = vi.mocked(currentAdmin);

const SESSION: AdminSession = {
  user: {
    id: "01JADMIN0000000000000000AA",
    name: "Ada Admin",
    email: "admin@example.com",
    email_verified: true,
    primary_role: "admin",
  },
  authorization: { roles: ["admin"], capabilities: ["admin.access"] },
};

function Probe() {
  const { status, session, can } = useSession();

  return (
    <div>
      <span data-testid="status">{status}</span>
      <span data-testid="name">{session?.user.name ?? "-"}</span>
      <span data-testid="can-admin">{String(can("admin.access"))}</span>
      <span data-testid="can-other">{String(can("users.manage"))}</span>
    </div>
  );
}

afterEach(() => {
  vi.clearAllMocks();
});

describe("SessionProvider", () => {
  it("resolves to authenticated and exposes capability checks", async () => {
    mockedCurrentAdmin.mockResolvedValue(SESSION);

    render(
      <SessionProvider>
        <Probe />
      </SessionProvider>,
    );

    await waitFor(() =>
      expect(screen.getByTestId("status")).toHaveTextContent("authenticated"),
    );
    expect(screen.getByTestId("name")).toHaveTextContent("Ada Admin");
    expect(screen.getByTestId("can-admin")).toHaveTextContent("true");
    expect(screen.getByTestId("can-other")).toHaveTextContent("false");
  });

  it("treats a 401 (no session) as guest", async () => {
    mockedCurrentAdmin.mockRejectedValue(
      new ApiError(401, "AUTHENTICATION_REQUIRED", "Unauthenticated."),
    );

    render(
      <SessionProvider>
        <Probe />
      </SessionProvider>,
    );

    await waitFor(() =>
      expect(screen.getByTestId("status")).toHaveTextContent("guest"),
    );
    expect(screen.getByTestId("name")).toHaveTextContent("-");
  });

  it("treats a 403 (capability revoked while signed in) as guest", async () => {
    mockedCurrentAdmin.mockRejectedValue(
      new ApiError(403, "AUTHORIZATION_DENIED", "This action is unauthorized."),
    );

    render(
      <SessionProvider>
        <Probe />
      </SessionProvider>,
    );

    await waitFor(() =>
      expect(screen.getByTestId("status")).toHaveTextContent("guest"),
    );
    expect(screen.getByTestId("can-admin")).toHaveTextContent("false");
  });
});
