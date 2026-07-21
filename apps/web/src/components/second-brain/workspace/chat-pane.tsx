"use client";

import { Alert, Button, cn, EmptyState, Textarea } from "@educonnect/ui";
import { useMutation } from "@tanstack/react-query";
import { MessagesSquare, Send, Sparkles } from "lucide-react";
import { useEffect, useRef, useState, type FormEvent } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { ApiError } from "@/lib/api/http";
import {
  MAX_STUDY_CHAT_HISTORY_TURNS,
  MAX_STUDY_CHAT_MESSAGE_CHARACTERS,
  sendStudyChatMessage,
  type StudyChatTurn,
} from "@/lib/api/study";

export type ChatPaneProps = {
  /** Knowledge item public id. The server resolves the document from it. */
  itemId: string;
  /** Shown above the composer so the student knows what is being asked. */
  documentTitle: string;
};

const FALLBACK_DISCLAIMER =
  "AI can be wrong. Answers come only from this document. Check anything that matters.";

/**
 * The workspace's right half: chat that knows this one document.
 *
 * History is client-held and bounded to 8 turns - ADR-0021 deliberately
 * rejected conversation persistence, so nothing here is stored anywhere and a
 * reload starts a fresh conversation. That is stated in the empty state rather
 * than left for the student to discover.
 *
 * Both halves of every turn are untrusted: the student's message is arbitrary
 * input and the reply is model output that may itself echo an injection
 * payload from the document. Both render through JSX text interpolation, which
 * escapes them, so neither can execute or restyle anything.
 */
export function ChatPane({ itemId, documentTitle }: ChatPaneProps) {
  const [turns, setTurns] = useState<StudyChatTurn[]>([]);
  const [draft, setDraft] = useState("");
  const [disclaimer, setDisclaimer] = useState(FALLBACK_DISCLAIMER);
  const logRef = useRef<HTMLDivElement>(null);

  const chatMutation = useMutation({
    mutationFn: (message: string) =>
      sendStudyChatMessage(itemId, message, turns),
    onSuccess: (reply, message) => {
      setDisclaimer(reply.disclaimer || FALLBACK_DISCLAIMER);
      setTurns(
        (current) =>
          [
            ...current,
            { role: "user", content: message },
            { role: "assistant", content: reply.reply },
          ].slice(-MAX_STUDY_CHAT_HISTORY_TURNS * 2) as StudyChatTurn[],
      );
      setDraft("");
    },
  });

  useEffect(() => {
    logRef.current?.scrollTo({ top: logRef.current.scrollHeight });
  }, [turns, chatMutation.isPending]);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const message = draft.trim();

    if (message === "" || chatMutation.isPending) {
      return;
    }

    chatMutation.mutate(message);
  };

  const apiError =
    chatMutation.error instanceof ApiError ? chatMutation.error : null;
  const unavailable = apiError?.status === 503;

  return (
    <div className="flex h-full min-h-0 flex-col">
      <header className="flex shrink-0 items-center gap-2.5 border-b border-border-subtle px-4 py-3 sm:px-5">
        <IconChip icon={MessagesSquare} accent="study" />
        <div className="min-w-0">
          <h2 className="text-h4 text-text-primary">Ask this document</h2>
          <p className="truncate text-caption text-text-muted">
            {documentTitle}
          </p>
        </div>
      </header>

      <div
        ref={logRef}
        role="log"
        aria-live="polite"
        aria-label="Conversation about this document"
        className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 sm:px-5"
      >
        {turns.length === 0 && !chatMutation.isPending ? (
          <EmptyState
            icon={Sparkles}
            title="Ask anything about this document"
            description="Answers come only from the text on the left. Nothing here is saved: reloading starts a fresh conversation."
          />
        ) : null}

        {turns.map((turn, index) => (
          <div
            key={`${index}-${turn.role}`}
            className={cn(
              "flex",
              turn.role === "user" ? "justify-end" : "justify-start",
            )}
          >
            <div
              className={cn(
                "max-w-[85%] whitespace-pre-wrap break-words rounded-lg px-3.5 py-2.5 text-body",
                turn.role === "user"
                  ? "bg-bg-interactive text-text-primary"
                  : "border border-status-ai/30 bg-bg-surface text-text-secondary",
              )}
            >
              <span className="sr-only">
                {turn.role === "user" ? "You said: " : "Assistant said: "}
              </span>
              {turn.content}
            </div>
          </div>
        ))}

        {chatMutation.isPending ? (
          <div className="flex justify-start">
            <div className="rounded-lg border border-status-ai/30 bg-bg-surface px-3.5 py-2.5 text-body text-text-muted">
              Reading the document…
            </div>
          </div>
        ) : null}
      </div>

      <form
        onSubmit={submit}
        className="shrink-0 space-y-2 border-t border-border-subtle px-4 py-3 sm:px-5"
      >
        {unavailable ? (
          <Alert variant="warning" title="Chat is unavailable right now">
            The assistant could not be reached. Nothing was made up in its
            place. Try again in a moment.
          </Alert>
        ) : chatMutation.error && !unavailable ? (
          <Alert variant="error" title="That question couldn't be sent">
            {chatMutation.error.message}
          </Alert>
        ) : null}

        <Textarea
          value={draft}
          maxLength={MAX_STUDY_CHAT_MESSAGE_CHARACTERS}
          aria-label="Ask a question about this document"
          placeholder="e.g. What is the main argument in section 3?"
          className="min-h-16"
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter" && (event.metaKey || event.ctrlKey)) {
              submit(event);
            }
          }}
        />

        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-caption text-text-muted">{disclaimer}</p>
          <Button
            type="submit"
            size="sm"
            isLoading={chatMutation.isPending}
            loadingLabel="Asking"
            disabled={draft.trim() === ""}
          >
            <Send aria-hidden="true" className="size-4" />
            <span className="ml-1.5">Ask</span>
          </Button>
        </div>
      </form>
    </div>
  );
}
