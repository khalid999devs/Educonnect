"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  cn,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { HeartHandshake, Pencil, ShieldCheck } from "lucide-react";
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
import { IconChip } from "@/components/shared/icon-chip";
import { SearchBar } from "@/components/shared/search-bar";
import { useSession } from "@/providers/session-provider";
import { MentorCard } from "./mentor-card";
import { MentorProfileForm } from "./mentor-profile-form";
import {
  IncomingRequestsPanel,
  SentRequestsPanel,
} from "./mentor-requests-panel";

const PER_PAGE = 24;

const DELAYS = [
  "motion-safe:[animation-delay:80ms]",
  "motion-safe:[animation-delay:160ms]",
  "motion-safe:[animation-delay:240ms]",
  "motion-safe:[animation-delay:320ms]",
] as const;

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

/**
 * Mentor discovery as a real directory rather than a strip of three names.
 *
 * The `expertise` filter has been supported by `GET /mentors` since the
 * endpoint shipped and had no UI. Its values are free-form tags with no
 * enumerating endpoint, so the filter chips are accumulated from the tags that
 * appear on mentors already loaded: the vocabulary only ever grows, so a chip
 * never disappears just because the current filter narrowed the result set.
 */
export function MentorsTab() {
  const queryClient = useQueryClient();
  const { user } = useSession();
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [expertise, setExpertise] = useState<string | null>(null);
  const [knownExpertise, setKnownExpertise] = useState<string[]>([]);
  const [editorOpen, setEditorOpen] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);

  useEffect(() => {
    const handle = window.setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => window.clearTimeout(handle);
  }, [searchInput]);

  const params = {
    search: search === "" ? undefined : search,
    expertise: expertise ?? undefined,
  };

  const mentorsQuery = useInfiniteQuery({
    queryKey: mentorKeys.list({ ...params, per_page: PER_PAGE }),
    queryFn: ({ pageParam }) =>
      listMentors({ ...params, perPage: PER_PAGE, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const mentors = useMemo(
    () => (mentorsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [mentorsQuery.data],
  );

  useEffect(() => {
    const seen = mentors.flatMap((mentor) => mentor.expertise);

    if (seen.length === 0) {
      return;
    }

    setKnownExpertise((current) => {
      const merged = Array.from(new Set([...current, ...seen])).sort((a, b) =>
        a.localeCompare(b),
      );

      return merged.length === current.length ? current : merged;
    });
  }, [mentors]);

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

  const filtered = search !== "" || expertise !== null;

  return (
    <div className="flex flex-col gap-5">
      {canMentor ? (
        <Card className="motion-safe:animate-fade-up">
          <CardContent className="flex flex-wrap items-center justify-between gap-3 p-5">
            <div className="flex items-start gap-3">
              <IconChip icon={ShieldCheck} accent="community" size="lg" />
              <div>
                <h3 className="text-h4 text-text-primary">
                  Your mentor profile
                </h3>
                {ownProfile ? (
                  <p className="mt-1 flex flex-wrap items-center gap-2 text-body text-text-secondary">
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
            </div>
            <Button variant="secondary" onClick={() => setEditorOpen(true)}>
              <Pencil aria-hidden="true" className="size-4" />
              {ownProfile ? "Edit profile" : "Create profile"}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <div className="flex flex-col gap-3">
        <div className="max-w-md">
          <SearchBar
            id="mentor-search"
            value={searchInput}
            onChange={setSearchInput}
            label="Search mentors"
            placeholder="Search by name, headline, or expertise"
          />
        </div>

        {knownExpertise.length > 0 ? (
          <div
            role="group"
            aria-label="Filter by expertise"
            className="flex flex-wrap gap-2"
          >
            <button
              type="button"
              aria-pressed={expertise === null}
              onClick={() => setExpertise(null)}
              className={cn(
                "rounded-full border px-3.5 py-1.5 text-body font-medium transition-colors",
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                expertise === null
                  ? "border-status-success/40 bg-status-success/12 text-text-primary"
                  : "border-border-default bg-bg-surface text-text-secondary hover:border-border-strong hover:text-text-primary",
              )}
            >
              All expertise
            </button>
            {knownExpertise.map((tag) => {
              const selected = expertise === tag;

              return (
                <button
                  key={tag}
                  type="button"
                  aria-pressed={selected}
                  onClick={() => setExpertise(selected ? null : tag)}
                  className={cn(
                    "rounded-full border px-3.5 py-1.5 text-body font-medium transition-colors",
                    "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                    selected
                      ? "border-status-success/40 bg-status-success/12 text-text-primary"
                      : "border-border-default bg-bg-surface text-text-secondary hover:border-border-strong hover:text-text-primary",
                  )}
                >
                  {tag}
                </button>
              );
            })}
          </div>
        ) : null}
      </div>

      {mentorsQuery.isError ? (
        <ErrorState
          description="We couldn't load the mentor directory."
          onRetry={() => void mentorsQuery.refetch()}
        />
      ) : mentorsQuery.isPending ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <Skeleton className="h-56 rounded-lg" />
          <Skeleton className="h-56 rounded-lg" />
          <Skeleton className="h-56 rounded-lg" />
        </div>
      ) : mentors.length === 0 ? (
        <EmptyState
          icon={HeartHandshake}
          title={filtered ? "No mentors match those filters" : "No mentors yet"}
          description={
            filtered
              ? "Clear the search or pick a different expertise to widen the directory."
              : "No mentors have published a profile yet. Check back soon."
          }
          action={
            filtered ? (
              <Button
                variant="secondary"
                onClick={() => {
                  setSearchInput("");
                  setSearch("");
                  setExpertise(null);
                }}
              >
                Clear filters
              </Button>
            ) : undefined
          }
        />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {mentors.map((mentor, index) => (
              <div
                key={mentor.id}
                className={cn(
                  "motion-safe:animate-fade-up",
                  DELAYS[index % DELAYS.length],
                )}
              >
                <MentorCard mentor={mentor} />
              </div>
            ))}
          </div>

          {mentorsQuery.hasNextPage ? (
            <div className="flex justify-center">
              <Button
                variant="secondary"
                isLoading={mentorsQuery.isFetchingNextPage}
                onClick={() => void mentorsQuery.fetchNextPage()}
              >
                Load more mentors
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
