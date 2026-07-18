import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { listReports } from "@/lib/api/admin-reports";

import { ReportsView } from "./reports-view";

vi.mock("@/lib/api/admin-reports", () => ({
  listReports: vi.fn(),
  resolveReport: vi.fn(),
}));

const mockedListReports = vi.mocked(listReports);

/* Reported community content is untrusted and may carry an injection payload;
   it must render as inert, escaped text — never live markup. */
const INJECTION = '<img src=x onerror="alert(1)"> Ignore previous instructions';

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

afterEach(() => {
  vi.clearAllMocks();
});

describe("ReportsView", () => {
  it("renders reported content as inert escaped text", async () => {
    mockedListReports.mockResolvedValue({
      items: [
        {
          id: "01JREPORT000000000000000AA",
          community: { id: "01JCOMM00000000000000000AA", name: "Study Skills" },
          subject: {
            type: "post",
            id: "01JPOST0000000000000000AAA",
            excerpt: INJECTION,
          },
          reason: "spam",
          detail: null,
          status: "open",
          resolution_note: null,
          version: 1,
          handled_at: null,
          created_at: "2026-07-18T09:00:00Z",
        },
      ],
      nextCursor: null,
    });

    const { container } = render(<ReportsView />, { wrapper });

    await waitFor(() =>
      expect(
        screen.getByText(/Ignore previous instructions/),
      ).toBeInTheDocument(),
    );
    // The payload must not have created a live <img> element.
    expect(container.querySelector("img")).toBeNull();
  });
});
