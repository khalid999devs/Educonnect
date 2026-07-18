"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { HeartHandshake, Pencil, Search } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { ApiError } from "@/lib/api/http";
import {
  createMentorProfile,
  getOwnMentorProfile,
  listMentors,
  updateMentorProfile,
  type MentorProfileInput,
} from "@/lib/api/mentors";
import { mentorKeys } from "@/lib/query-keys";
import { useSession } from "@/providers/session-provider";
import { MentorCard } from "./mentor-card";
import { MentorProfileForm } from "./mentor-profile-form";
import {
  IncomingRequestsPanel,
  SentRequestsPanel,
} from "./mentor-requests-panel";

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

export function MentorsView() {
  const queryClient = useQueryClient();
  const { user } = useSession();
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [editorOpen, setEditorOpen] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);

  useEffect(() => {
    const handle = window.setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => window.clearTimeout(handle);
  }, [searchInput]);

  const params = { search: search === "" ? undefined : search };
  const mentorsQuery = useInfiniteQuery({
    queryKey: mentorKeys.list(params),
    queryFn: ({ pageParam }) => listMentors({ ...params, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });
  const mentors = useMemo(
    () => (mentorsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [mentorsQuery.data],
  );

  const ownProfileQuery = useQuery({
    queryKey: mentorKeys.ownProfile(),
    queryFn: () => getOwnMentorProfile(),
  });
  const ownProfile = ownProfileQuery.data ?? null;
  const canMentor = user?.primary_role === "mentor" || ownProfile !== null;

  const saveMutation = useMutation({
    mutationFn: (input: MentorProfileInput) =>
      ownProfile
        ? updateMentorProfile({
            ...input,
            expected_version: ownProfile.version,
          })
        : createMentorProfile(input),
    onMutate: () => setProfileError(null),
    onSuccess: () => {
      setEditorOpen(false);
      void queryClient.invalidateQueries({ queryKey: mentorKeys.all });
    },
    onError: (error) => setProfileError(messageFrom(error)),
  });

  return (
    <div className="mx-auto flex w-full max-w-360 flex-col gap-6">
      <header className="rounded-xl border border-border-subtle bg-bg-surface p-6">
        <h1 className="text-h2 text-text-primary">Mentors</h1>
        <p className="mt-1 max-w-2xl text-body-lg text-text-secondary">
          When tools, prompts, and your community aren&apos;t enough, ask a
          mentor for help. Request help directly — no bookings or payments.
        </p>
      </header>

      {canMentor ? (
        <Card>
          <CardContent className="flex flex-wrap items-center justify-between gap-3 p-5">
            <div>
              <h2 className="text-h4 text-text-primary">Your mentor profile</h2>
              {ownProfile ? (
                <p className="mt-1 flex items-center gap-2 text-body text-text-secondary">
                  {ownProfile.headline}
                  <Badge
                    variant={
                      ownProfile.verification_state === "verified"
                        ? "success"
                        : "neutral"
                    }
                  >
                    {ownProfile.verification_state === "verified"
                      ? "Verified"
                      : "Awaiting verification"}
                  </Badge>
                </p>
              ) : (
                <p className="mt-1 text-body text-text-secondary">
                  Publish a profile so students can find you.
                </p>
              )}
            </div>
            <Button variant="secondary" onClick={() => setEditorOpen(true)}>
              <Pencil className="size-4" aria-hidden />
              {ownProfile ? "Edit profile" : "Create profile"}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <div className="max-w-md">
        <FormField id="mentor-search" label="Find a mentor">
          {(control) => (
            <span className="relative block">
              <Search
                className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
                aria-hidden
              />
              <Input
                {...control}
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search by name or expertise"
                className="pl-9"
              />
            </span>
          )}
        </FormField>
      </div>

      {mentorsQuery.isError ? (
        <ErrorState
          description="We couldn't load the mentor directory."
          onRetry={() => void mentorsQuery.refetch()}
        />
      ) : mentorsQuery.isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Skeleton className="h-48 rounded-lg" />
          <Skeleton className="h-48 rounded-lg" />
          <Skeleton className="h-48 rounded-lg" />
        </div>
      ) : mentors.length === 0 ? (
        <EmptyState
          icon={HeartHandshake}
          title="No mentors yet"
          description="No mentors have published a profile that matches. Check back soon."
        />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {mentors.map((mentor) => (
              <MentorCard key={mentor.id} mentor={mentor} />
            ))}
          </div>
          {mentorsQuery.hasNextPage ? (
            <div className="flex justify-center">
              <Button
                variant="secondary"
                isLoading={mentorsQuery.isFetchingNextPage}
                onClick={() => void mentorsQuery.fetchNextPage()}
              >
                Load more
              </Button>
            </div>
          ) : null}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <SentRequestsPanel />
        {canMentor && ownProfile ? <IncomingRequestsPanel /> : null}
      </div>

      <MentorProfileForm
        open={editorOpen}
        initial={ownProfile}
        isSubmitting={saveMutation.isPending}
        error={profileError}
        onClose={() => setEditorOpen(false)}
        onSubmit={(input) => saveMutation.mutate(input)}
      />
    </div>
  );
}
