"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  Dialog,
  EmptyState,
  FormField,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { ApiError } from "@/lib/api/http";
import {
  listIncomingRequests,
  listSentRequests,
  transitionMentorRequest,
  type MentorRequest,
  type MentorRequestStatus,
} from "@/lib/api/mentors";
import { mentorKeys } from "@/lib/query-keys";
import { formatRelativeTime } from "@/lib/format";

const STATUS_VARIANT: Record<
  MentorRequestStatus,
  "info" | "success" | "brand" | "neutral"
> = {
  open: "info",
  accepted: "success",
  completed: "brand",
  declined: "neutral",
  withdrawn: "neutral",
};

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

function StatusBadge({ status }: { status: MentorRequestStatus }) {
  return (
    <Badge variant={STATUS_VARIANT[status]}>
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </Badge>
  );
}

export function SentRequestsPanel() {
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: mentorKeys.sentRequests(),
    queryFn: () => listSentRequests({ perPage: 20 }),
  });

  const withdrawMutation = useMutation({
    mutationFn: (request: MentorRequest) =>
      transitionMentorRequest(request.id, {
        action: "withdraw",
        expected_version: request.version,
      }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: mentorKeys.all }),
  });

  const requests = query.data?.data ?? [];

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-5">
        <h2 className="text-h4 text-text-primary">Your help requests</h2>
        {query.isPending ? (
          <Skeleton className="h-16 rounded-lg" />
        ) : requests.length === 0 ? (
          <EmptyState
            title="No requests yet"
            description="When you ask a mentor for help, it will appear here."
          />
        ) : (
          <ul className="flex flex-col gap-2">
            {requests.map((request) => (
              <li
                key={request.id}
                className="rounded-lg border border-border-subtle p-3"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium text-text-primary">
                    {request.subject}
                  </span>
                  <StatusBadge status={request.status} />
                </div>
                <p className="text-caption text-text-muted">
                  To {request.mentor?.name ?? "a mentor"} ·{" "}
                  {formatRelativeTime(request.created_at)}
                </p>
                {request.response_note ? (
                  <p className="mt-1 rounded-md bg-bg-subtle px-2 py-1 text-caption text-text-secondary">
                    {request.response_note}
                  </p>
                ) : null}
                {request.status === "open" ? (
                  <div className="mt-2">
                    <Button
                      variant="ghost"
                      size="sm"
                      isLoading={withdrawMutation.isPending}
                      onClick={() => withdrawMutation.mutate(request)}
                    >
                      Withdraw
                    </Button>
                  </div>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

export function IncomingRequestsPanel() {
  const queryClient = useQueryClient();
  const [responding, setResponding] = useState<{
    request: MentorRequest;
    action: "accept" | "decline";
  } | null>(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);

  const query = useQuery({
    queryKey: mentorKeys.incomingRequests(),
    queryFn: () => listIncomingRequests({ perPage: 20 }),
  });

  const transitionMutation = useMutation({
    mutationFn: ({
      request,
      action,
      responseNote,
    }: {
      request: MentorRequest;
      action: "accept" | "decline" | "complete";
      responseNote: string | null;
    }) =>
      transitionMentorRequest(request.id, {
        action,
        response_note: responseNote,
        expected_version: request.version,
      }),
    onMutate: () => setError(null),
    onSuccess: () => {
      setResponding(null);
      setNote("");
      void queryClient.invalidateQueries({ queryKey: mentorKeys.all });
    },
    onError: (mutationError) => setError(messageFrom(mutationError)),
  });

  const requests = query.data?.data ?? [];

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-5">
        <h2 className="text-h4 text-text-primary">Requests for you</h2>
        {query.isPending ? (
          <Skeleton className="h-16 rounded-lg" />
        ) : requests.length === 0 ? (
          <EmptyState
            title="No incoming requests"
            description="Students who ask you for help will appear here."
          />
        ) : (
          <ul className="flex flex-col gap-2">
            {requests.map((request) => (
              <li
                key={request.id}
                className="rounded-lg border border-border-subtle p-3"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium text-text-primary">
                    {request.subject}
                  </span>
                  <StatusBadge status={request.status} />
                </div>
                <p className="text-caption text-text-muted">
                  From {request.requester?.name ?? "a student"} ·{" "}
                  {formatRelativeTime(request.created_at)}
                </p>
                <p className="mt-1 whitespace-pre-wrap break-words text-body text-text-secondary">
                  {request.message}
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {request.status === "open" ? (
                    <>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() =>
                          setResponding({ request, action: "accept" })
                        }
                      >
                        Accept
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                          setResponding({ request, action: "decline" })
                        }
                      >
                        Decline
                      </Button>
                    </>
                  ) : null}
                  {request.status === "accepted" ? (
                    <Button
                      variant="secondary"
                      size="sm"
                      isLoading={
                        transitionMutation.isPending &&
                        transitionMutation.variables?.request.id === request.id
                      }
                      onClick={() =>
                        transitionMutation.mutate({
                          request,
                          action: "complete",
                          responseNote: null,
                        })
                      }
                    >
                      Mark complete
                    </Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <Dialog
        open={responding !== null}
        onClose={() => setResponding(null)}
        title={
          responding?.action === "accept"
            ? "Accept this request"
            : "Decline this request"
        }
        description="You can add a short note for the student."
        footer={
          <>
            <Button variant="ghost" onClick={() => setResponding(null)}>
              Cancel
            </Button>
            <Button
              variant={
                responding?.action === "accept" ? "primary" : "destructive"
              }
              isLoading={transitionMutation.isPending}
              loadingLabel="Sending"
              onClick={() => {
                if (responding) {
                  transitionMutation.mutate({
                    request: responding.request,
                    action: responding.action,
                    responseNote: note.trim() === "" ? null : note.trim(),
                  });
                }
              }}
            >
              {responding?.action === "accept" ? "Accept" : "Decline"}
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-3">
          {error ? (
            <Alert variant="error" title="Could not respond">
              {error}
            </Alert>
          ) : null}
          <FormField id="respond-note" label="Note" hint="Optional.">
            {(control) => (
              <Textarea
                {...control}
                value={note}
                rows={3}
                maxLength={2000}
                onChange={(event) => setNote(event.target.value)}
              />
            )}
          </FormField>
        </div>
      </Dialog>
    </Card>
  );
}
