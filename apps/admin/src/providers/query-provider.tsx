"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";

import { ApiError } from "@/lib/api/http";

/**
 * Server-state boundary for the console (ADR-0022 pattern). Client errors
 * (4xx) are final - retrying cannot fix validation, auth, or authorization
 * failures; transient network/5xx failures retry twice.
 */
export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            retry: (failureCount, error) => {
              if (error instanceof ApiError && error.status < 500) {
                return false;
              }

              return failureCount < 2;
            },
          },
          mutations: { retry: false },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
