"use client";

import {
  Alert,
  Badge,
  Button,
  Dialog,
  ErrorState,
  Select,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { useState } from "react";

import {
  listMentors,
  setMentorVerification,
  type MentorFilters,
  type MentorProfile,
} from "@/lib/api/admin-mentors";
import { ApiError } from "@/lib/api/http";
import { mentorKeys } from "@/lib/query-keys";

export function MentorsView() {
  const queryClient = useQueryClient();
  const [verificationState, setVerificationState] = useState("");
  const [filters, setFilters] = useState<MentorFilters>({});
  const [target, setTarget] = useState<MentorProfile | null>(null);

  const query = useInfiniteQuery({
    queryKey: mentorKeys.list(filters),
    queryFn: ({ pageParam }) => listMentors({ ...filters, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const mentors = query.data?.pages.flatMap((page) => page.items) ?? [];

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Mentors</h1>
        <p className="text-body text-text-secondary">
          Review mentor profiles and set their verification status. Verification
          is recorded in the audit log.
        </p>
      </header>

      <div className="w-56">
        <Select
          value={verificationState}
          onChange={(event) => {
            setVerificationState(event.target.value);
            setFilters({ verificationState: event.target.value || undefined });
          }}
          aria-label="Filter by verification"
        >
          <option value="">All mentors</option>
          <option value="unverified">Unverified</option>
          <option value="verified">Verified</option>
        </Select>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load mentors"
          description="The mentor list could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : mentors.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No mentor profiles yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {mentors.map((mentor) => (
            <li
              key={mentor.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border-subtle bg-bg-surface px-4 py-3"
            >
              <div className="min-w-0">
                <p className="font-medium text-text-primary">{mentor.name}</p>
                <p className="truncate text-caption text-text-muted">
                  {mentor.headline}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <Badge
                  variant={
                    mentor.verification_state === "verified"
                      ? "success"
                      : "neutral"
                  }
                >
                  {mentor.verification_state === "verified"
                    ? "Verified"
                    : "Unverified"}
                </Badge>
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => setTarget(mentor)}
                >
                  {mentor.verification_state === "verified"
                    ? "Revoke"
                    : "Verify"}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {query.hasNextPage ? (
        <div className="flex justify-center">
          <Button
            variant="secondary"
            isLoading={query.isFetchingNextPage}
            onClick={() => void query.fetchNextPage()}
          >
            Load more
          </Button>
        </div>
      ) : null}

      {target ? (
        <VerificationDialog
          mentor={target}
          onClose={() => setTarget(null)}
          onDone={() => {
            setTarget(null);
            void queryClient.invalidateQueries({ queryKey: mentorKeys.all });
          }}
        />
      ) : null}
    </div>
  );
}

function VerificationDialog({
  mentor,
  onClose,
  onDone,
}: {
  mentor: MentorProfile;
  onClose: () => void;
  onDone: () => void;
}) {
  const [reason, setReason] = useState("");
  const nextState =
    mentor.verification_state === "verified" ? "unverified" : "verified";

  const mutation = useMutation({
    mutationFn: () =>
      setMentorVerification({
        id: mentor.id,
        verificationState: nextState,
        expectedVersion: mentor.version,
        reason: reason.trim(),
      }),
    onSuccess: onDone,
  });

  return (
    <Dialog
      open
      title={nextState === "verified" ? "Verify mentor" : "Revoke verification"}
      onClose={onClose}
    >
      <div className="space-y-4">
        <p className="text-body text-text-secondary">
          {nextState === "verified"
            ? `Mark ${mentor.name} as a verified mentor.`
            : `Remove the verified badge from ${mentor.name}.`}
        </p>
        {mutation.isError ? (
          <Alert variant="error" title="Could not update verification">
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
            Confirm
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
