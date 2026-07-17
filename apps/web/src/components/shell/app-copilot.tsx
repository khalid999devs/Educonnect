"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  CopilotTrigger,
  Spinner,
  Textarea,
} from "@educonnect/ui";
import { Send, Sparkles } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import {
  getCopilotAvailability,
  sendCopilotMessage,
  type CopilotTurn,
} from "@/lib/api/copilot";
import { ApiError } from "@/lib/api/http";

const STARTERS = [
  "What should I do next?",
  "Explain this page",
  "Summarize my week",
];

type Availability = "unknown" | "checking" | "enabled" | "disabled";

/**
 * The single Copilot mount for the student shell: a bounded advisory chat
 * over the user's own workspace snapshot. It reads; it never changes
 * records — and it says so.
 */
export function AppCopilot() {
  const [isOpen, setIsOpen] = useState(false);
  const [availability, setAvailability] = useState<Availability>("unknown");
  const [turns, setTurns] = useState<CopilotTurn[]>([]);
  const [draft, setDraft] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const logRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsOpen(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);

    return () => window.removeEventListener("keydown", onKeyDown);
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen || availability !== "unknown") {
      return;
    }

    setAvailability("checking");
    getCopilotAvailability()
      .then((result) =>
        setAvailability(result.enabled ? "enabled" : "disabled"),
      )
      .catch(() => setAvailability("disabled"));
  }, [isOpen, availability]);

  useEffect(() => {
    logRef.current?.scrollTo({ top: logRef.current.scrollHeight });
  }, [turns, busy]);

  const send = async (message: string) => {
    const trimmed = message.trim();

    if (trimmed === "" || busy) {
      return;
    }

    setError(null);
    setBusy(true);
    setDraft("");
    setTurns((current) => [...current, { role: "user", content: trimmed }]);

    try {
      const result = await sendCopilotMessage(trimmed, turns);
      setTurns((current) => [
        ...current,
        { role: "assistant", content: result.reply },
      ]);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught.status === 503
            ? "The Copilot provider is unavailable right now — try again shortly."
            : caught.message
          : "Could not reach the server.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      {isOpen ? (
        <section
          aria-label="EduConnect Copilot"
          className="fixed bottom-24 right-5 z-50 w-96 max-w-[calc(100vw-2.5rem)]"
        >
          <Card className="flex max-h-[70dvh] flex-col border-status-ai/30 bg-bg-surface p-0 shadow-glow-sm">
            <CardHeader className="mb-0 border-b border-border-subtle p-4">
              <div className="flex items-center justify-between gap-2">
                <CardTitle as="h2" className="flex items-center gap-2">
                  <Sparkles
                    aria-hidden="true"
                    className="size-4 text-status-ai"
                  />
                  Copilot
                </CardTitle>
                <Badge variant="ai">AI · advisory only</Badge>
              </div>
            </CardHeader>

            <CardContent className="flex min-h-0 flex-1 flex-col p-4">
              {availability === "checking" || availability === "unknown" ? (
                <div className="flex justify-center py-6">
                  <Spinner size="md" label="Checking Copilot availability" />
                </div>
              ) : null}

              {availability === "disabled" ? (
                <p className="text-body text-text-secondary">
                  The Copilot isn't configured on this server — it needs an AI
                  provider key. Everything else works fully without it.
                </p>
              ) : null}

              {availability === "enabled" ? (
                <>
                  <div
                    ref={logRef}
                    className="min-h-0 flex-1 space-y-2.5 overflow-y-auto pr-1"
                    aria-live="polite"
                  >
                    {turns.length === 0 ? (
                      <div className="space-y-2.5">
                        <p className="text-body text-text-secondary">
                          I can explain what you're seeing, summarize your own
                          workspace, and suggest a real next step. I never
                          change your records.
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                          {STARTERS.map((starter) => (
                            <button
                              key={starter}
                              type="button"
                              onClick={() => void send(starter)}
                              className="rounded-full border border-border-default px-3 py-1 text-caption text-text-secondary transition-colors hover:border-brand-focus hover:text-text-primary"
                            >
                              {starter}
                            </button>
                          ))}
                        </div>
                      </div>
                    ) : (
                      turns.map((turn, index) => (
                        <div
                          key={`${turn.role}-${index}`}
                          className={cn(
                            "max-w-[85%] whitespace-pre-wrap rounded-lg px-3 py-2 text-body",
                            turn.role === "user"
                              ? "ml-auto bg-brand-primary text-white"
                              : "bg-bg-interactive text-text-primary",
                          )}
                        >
                          {turn.content}
                        </div>
                      ))
                    )}
                    {busy ? (
                      <div className="flex items-center gap-2 text-caption text-text-muted">
                        <Spinner size="sm" />
                        Thinking…
                      </div>
                    ) : null}
                  </div>

                  {error ? (
                    <Alert
                      variant="error"
                      title="Copilot error"
                      className="mt-2.5"
                    >
                      {error}
                    </Alert>
                  ) : null}

                  <form
                    className="mt-2.5 flex items-end gap-2"
                    onSubmit={(event) => {
                      event.preventDefault();
                      void send(draft);
                    }}
                  >
                    <Textarea
                      aria-label="Message the Copilot"
                      placeholder="Ask about your workspace…"
                      value={draft}
                      rows={2}
                      maxLength={1000}
                      onChange={(event) => setDraft(event.target.value)}
                      onKeyDown={(event) => {
                        if (event.key === "Enter" && !event.shiftKey) {
                          event.preventDefault();
                          void send(draft);
                        }
                      }}
                      className="min-h-11 flex-1"
                    />
                    <Button
                      type="submit"
                      size="sm"
                      isLoading={busy}
                      disabled={draft.trim() === ""}
                      aria-label="Send message"
                    >
                      <Send aria-hidden="true" className="size-4" />
                    </Button>
                  </form>
                  <p className="mt-1.5 text-caption text-text-muted">
                    AI-generated — verify important details. It reads a summary
                    of your workspace and never changes records.
                  </p>
                </>
              ) : null}
            </CardContent>
          </Card>
        </section>
      ) : null}
      <CopilotTrigger
        variant="pill"
        isOpen={isOpen}
        onToggle={() => setIsOpen((open) => !open)}
      />
    </>
  );
}
