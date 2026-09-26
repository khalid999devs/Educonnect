"use client";

import { Alert, Badge, Button, Dialog, Textarea } from "@educonnect/ui";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import {
  availableTransitions,
  transitionContent,
  type ContentState,
  type ContentTransition,
  type ContentType,
} from "@/lib/api/admin-content";
import { ApiError } from "@/lib/api/http";
import { humanizeKey } from "@/lib/format";

const STATE_BADGE: Record<ContentState, "neutral" | "warning" | "success"> = {
  draft: "neutral",
  in_review: "warning",
  published: "success",
  archived: "neutral",
};

const TRANSITION_LABEL: Record<ContentTransition, string> = {
  submit_for_review: "Submit for review",
  publish: "Publish",
  archive: "Archive",
  return_to_draft: "Return to draft",
};

export function StateBadge({ state }: { state: ContentState }) {
  return <Badge variant={STATE_BADGE[state]}>{humanizeKey(state)}</Badge>;
}

/**
 * Lifecycle transition buttons for a single content record. Each transition
 * takes an audited reason. The available transitions mirror the API state
 * machine, so an illegal move is never offered.
 */
export function LifecycleControls({
  type,
  id,
  state,
  version,
  invalidateKey,
}: {
  type: ContentType;
  id: string;
  state: ContentState;
  version: number;
  invalidateKey: readonly unknown[];
}) {
  const queryClient = useQueryClient();
  const [pending, setPending] = useState<ContentTransition | null>(null);

  return (
    <>
      <span className="flex flex-wrap gap-2">
        {availableTransitions(state).map((transition) => (
          <Button
            key={transition}
            size="sm"
            variant={transition === "archive" ? "destructive" : "secondary"}
            onClick={() => setPending(transition)}
          >
            {TRANSITION_LABEL[transition]}
          </Button>
        ))}
      </span>

      {pending ? (
        <TransitionDialog
          type={type}
          id={id}
          transition={pending}
          version={version}
          onClose={() => setPending(null)}
          onDone={() => {
            setPending(null);
            void queryClient.invalidateQueries({ queryKey: invalidateKey });
          }}
        />
      ) : null}
    </>
  );
}

function TransitionDialog({
  type,
  id,
  transition,
  version,
  onClose,
  onDone,
}: {
  type: ContentType;
  id: string;
  transition: ContentTransition;
  version: number;
  onClose: () => void;
  onDone: () => void;
}) {
  const [reason, setReason] = useState("");
  const mutation = useMutation({
    mutationFn: () =>
      transitionContent({
        type,
        id,
        transition,
        expectedVersion: version,
        reason: reason.trim(),
      }),
    onSuccess: onDone,
  });

  return (
    <Dialog open title={TRANSITION_LABEL[transition]} onClose={onClose}>
      <div className="space-y-4">
        <p className="text-body text-text-secondary">
          This lifecycle change is recorded in the audit log.
        </p>
        {mutation.isError ? (
          <Alert variant="error" title="Could not change state">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}
        <label className="block">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Reason (recorded in the audit log)
          </span>
          <Textarea
            rows={2}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            disabled={reason.trim().length === 0}
            onClick={() => mutation.mutate()}
          >
            {TRANSITION_LABEL[transition]}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
