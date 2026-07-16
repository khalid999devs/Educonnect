"use client";

import { ErrorState } from "@educonnect/ui";

export default function RootError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <main className="flex min-h-dvh items-center justify-center px-6">
      <ErrorState
        title="Something went wrong"
        description="An unexpected error interrupted this page. Your data is unaffected."
        onRetry={reset}
        className="w-full max-w-lg"
      />
    </main>
  );
}
