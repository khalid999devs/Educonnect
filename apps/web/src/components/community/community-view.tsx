"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
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
import { ArrowRight, Users } from "lucide-react";
import Link from "next/link";
import { useMemo, useState } from "react";

import {
  createPost,
  deletePost,
  joinCommunity,
  leaveCommunity,
  listCommunities,
  listFeed,
  reportPost,
  updatePost,
  type Community,
  type CreatePostInput,
  type Post,
  type ReportReason,
} from "@/lib/api/community";
import { ApiError } from "@/lib/api/http";
import { listMentors } from "@/lib/api/mentors";
import { communityKeys, mentorKeys } from "@/lib/query-keys";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { PostCard } from "./post-card";
import { PostComposer } from "./post-composer";
import { PostEditDialog } from "./post-edit-dialog";
import { ReportDialog } from "./report-dialog";

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

export function CommunityView() {
  const queryClient = useQueryClient();
  const [composerError, setComposerError] = useState<string | null>(null);
  const [reporting, setReporting] = useState<Post | null>(null);
  const [reportError, setReportError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Post | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<Post | null>(null);
  const [busyCommunityId, setBusyCommunityId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const communitiesQuery = useQuery({
    queryKey: communityKeys.communities({ perPage: 20 }),
    queryFn: () => listCommunities({ perPage: 20 }),
  });
  const communities = useMemo(
    () => communitiesQuery.data?.data ?? [],
    [communitiesQuery.data],
  );
  const joined = useMemo(
    () => communities.filter((community) => community.is_member),
    [communities],
  );

  const feedQuery = useInfiniteQuery({
    queryKey: communityKeys.feed(),
    queryFn: ({ pageParam }) => listFeed({ cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });
  const feedPosts = useMemo(
    () => (feedQuery.data?.pages ?? []).flatMap((page) => page.data),
    [feedQuery.data],
  );

  const mentorsQuery = useQuery({
    queryKey: mentorKeys.list({ perPage: 3 }),
    queryFn: () => listMentors({ perPage: 3 }),
  });

  function flashNotice(message: string) {
    setNotice(message);
    window.setTimeout(() => setNotice(null), 4000);
  }

  const createPostMutation = useMutation({
    mutationFn: ({
      communityId,
      input,
    }: {
      communityId: string;
      input: CreatePostInput;
    }) => createPost(communityId, input),
    onMutate: () => setComposerError(null),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: communityKeys.all });
      flashNotice("Your post is live.");
    },
    onError: (error) => setComposerError(messageFrom(error)),
  });

  const membershipMutation = useMutation({
    mutationFn: ({ community }: { community: Community }) =>
      community.is_member
        ? leaveCommunity(community.id)
        : joinCommunity(community.id),
    onMutate: ({ community }) => setBusyCommunityId(community.id),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: communityKeys.all }),
    onSettled: () => setBusyCommunityId(null),
  });

  const reportMutation = useMutation({
    mutationFn: ({
      post,
      reason,
      detail,
    }: {
      post: Post;
      reason: ReportReason;
      detail: string | null;
    }) => reportPost(post.id, { reason, detail }),
    onMutate: () => setReportError(null),
    onSuccess: () => {
      setReporting(null);
      flashNotice("Thanks. The moderators will review this.");
    },
    onError: (error) => setReportError(messageFrom(error)),
  });

  const editMutation = useMutation({
    mutationFn: ({
      post,
      input,
    }: {
      post: Post;
      input: { title: string | null; body: string };
    }) => updatePost(post.id, { ...input, expected_version: post.version }),
    onMutate: () => setEditError(null),
    onSuccess: () => {
      setEditing(null);
      void queryClient.invalidateQueries({ queryKey: communityKeys.all });
    },
    onError: (error) => setEditError(messageFrom(error)),
  });

  const deleteMutation = useMutation({
    mutationFn: (post: Post) => deletePost(post.id, post.version),
    onSuccess: () => {
      setDeleting(null);
      void queryClient.invalidateQueries({ queryKey: communityKeys.all });
    },
  });

  return (
    <div className="mx-auto flex w-full max-w-360 flex-col gap-6">
      <header className="rounded-xl border border-border-subtle bg-bg-surface p-6">
        <h1 className="text-h2 text-text-primary">Community</h1>
        <p className="mt-1 max-w-2xl text-body-lg text-text-secondary">
          Curated spaces to ask questions, share resources, and learn alongside
          other students.
        </p>
      </header>

      {notice ? <Alert variant="success" title={notice} /> : null}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="flex flex-col gap-4">
          {joined.length > 0 ? (
            <PostComposer
              communities={joined}
              isSubmitting={createPostMutation.isPending}
              error={composerError}
              onSubmit={(communityId, input) =>
                createPostMutation.mutate({ communityId, input })
              }
            />
          ) : (
            <Alert variant="info" title="Join a community to start posting">
              Pick a community from the list to see its feed and share updates.
            </Alert>
          )}

          {feedQuery.isError ? (
            <ErrorState
              description="We couldn't load your feed."
              onRetry={() => void feedQuery.refetch()}
            />
          ) : feedQuery.isPending ? (
            <div className="flex flex-col gap-3">
              <Skeleton className="h-40 rounded-lg" />
              <Skeleton className="h-40 rounded-lg" />
            </div>
          ) : feedPosts.length === 0 ? (
            <EmptyState
              icon={Users}
              title={joined.length > 0 ? "No posts yet" : "Your feed is empty"}
              description={
                joined.length > 0
                  ? "Be the first to post in one of your communities."
                  : "Join a community to see posts from other students here."
              }
            />
          ) : (
            <div className="flex flex-col gap-3">
              {feedPosts.map((post) => (
                <PostCard
                  key={post.id}
                  post={post}
                  onReport={setReporting}
                  onEdit={setEditing}
                  onDelete={setDeleting}
                  busy={editMutation.isPending || deleteMutation.isPending}
                />
              ))}
              {feedQuery.hasNextPage ? (
                <div className="flex justify-center">
                  <Button
                    variant="secondary"
                    isLoading={feedQuery.isFetchingNextPage}
                    onClick={() => void feedQuery.fetchNextPage()}
                  >
                    Load more
                  </Button>
                </div>
              ) : null}
            </div>
          )}
        </div>

        <aside className="flex flex-col gap-4">
          <Card>
            <CardHeader>
              <CardTitle as="h2">Communities</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              {communitiesQuery.isPending ? (
                <>
                  <Skeleton className="h-14 rounded-lg" />
                  <Skeleton className="h-14 rounded-lg" />
                </>
              ) : communitiesQuery.isError ? (
                <ErrorState
                  description="Couldn't load communities."
                  onRetry={() => void communitiesQuery.refetch()}
                />
              ) : communities.length === 0 ? (
                <p className="text-caption text-text-muted">
                  No communities are available yet.
                </p>
              ) : (
                communities.map((community) => (
                  <div
                    key={community.id}
                    className="flex items-start justify-between gap-3 rounded-lg border border-border-subtle p-3"
                  >
                    <div className="min-w-0">
                      <Link
                        href={`/community/groups/${community.id}`}
                        className="font-medium text-text-primary hover:text-brand-primary"
                      >
                        {community.name}
                      </Link>
                      <p className="line-clamp-2 text-caption text-text-muted">
                        {community.summary}
                      </p>
                    </div>
                    <Button
                      variant={community.is_member ? "ghost" : "secondary"}
                      size="sm"
                      isLoading={busyCommunityId === community.id}
                      onClick={() => membershipMutation.mutate({ community })}
                    >
                      {community.is_member ? "Joined" : "Join"}
                    </Button>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle as="h2">Featured mentors</CardTitle>
              <Link
                href="/mentors"
                className="inline-flex items-center gap-1 text-caption font-medium text-brand-primary hover:underline"
              >
                View all <ArrowRight className="size-3.5" aria-hidden />
              </Link>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              {mentorsQuery.isPending ? (
                <Skeleton className="h-12 rounded-lg" />
              ) : (mentorsQuery.data?.data.length ?? 0) === 0 ? (
                <p className="text-caption text-text-muted">
                  No mentors have published a profile yet.
                </p>
              ) : (
                mentorsQuery.data?.data.map((mentor) => (
                  <Link
                    key={mentor.id}
                    href={`/mentors/${mentor.id}`}
                    className="flex flex-col rounded-lg border border-border-subtle p-3 transition-colors hover:border-border-strong"
                  >
                    <span className="flex items-center gap-2 font-medium text-text-primary">
                      {mentor.name}
                      {mentor.verification_state === "verified" ? (
                        <Badge variant="success">Verified</Badge>
                      ) : null}
                    </span>
                    <span className="line-clamp-1 text-caption text-text-muted">
                      {mentor.headline}
                    </span>
                  </Link>
                ))
              )}
            </CardContent>
          </Card>
        </aside>
      </div>

      <ReportDialog
        open={reporting !== null}
        subjectLabel="post"
        isSubmitting={reportMutation.isPending}
        error={reportError}
        onClose={() => setReporting(null)}
        onSubmit={(reason, detail) => {
          if (reporting) {
            reportMutation.mutate({ post: reporting, reason, detail });
          }
        }}
      />

      <PostEditDialog
        post={editing}
        isSubmitting={editMutation.isPending}
        error={editError}
        onClose={() => setEditing(null)}
        onSubmit={(input) => {
          if (editing) {
            editMutation.mutate({ post: editing, input });
          }
        }}
      />

      <ConfirmDialog
        open={deleting !== null}
        title="Delete this post?"
        description="Your post will be removed from the community feed. This cannot be undone."
        confirmLabel="Delete post"
        busy={deleteMutation.isPending}
        error={
          deleteMutation.isError ? messageFrom(deleteMutation.error) : null
        }
        onConfirm={() => {
          if (deleting) {
            deleteMutation.mutate(deleting);
          }
        }}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
