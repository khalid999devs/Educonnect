"use client";

import {
  Alert,
  Badge,
  Button,
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
import { ArrowLeft, MessageSquare } from "lucide-react";
import Link from "next/link";
import { useMemo, useState } from "react";

import {
  createPost,
  deletePost,
  getCommunity,
  joinCommunity,
  leaveCommunity,
  listCommunityPosts,
  reportPost,
  updatePost,
  type CreatePostInput,
  type Post,
  type ReportReason,
} from "@/lib/api/community";
import { ApiError } from "@/lib/api/http";
import { communityKeys } from "@/lib/query-keys";
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

export function CommunityDetail({ communityId }: { communityId: string }) {
  const queryClient = useQueryClient();
  const [composerError, setComposerError] = useState<string | null>(null);
  const [reporting, setReporting] = useState<Post | null>(null);
  const [reportError, setReportError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Post | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<Post | null>(null);

  const communityQuery = useQuery({
    queryKey: communityKeys.community(communityId),
    queryFn: () => getCommunity(communityId),
  });
  const community = communityQuery.data;

  const postsQuery = useInfiniteQuery({
    queryKey: communityKeys.posts(communityId),
    queryFn: ({ pageParam }) =>
      listCommunityPosts(communityId, { cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
    enabled: community !== undefined,
  });
  const posts = useMemo(
    () => (postsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [postsQuery.data],
  );

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: communityKeys.all });

  const membershipMutation = useMutation({
    mutationFn: () =>
      community?.is_member
        ? leaveCommunity(communityId)
        : joinCommunity(communityId),
    onSuccess: invalidate,
  });

  const createPostMutation = useMutation({
    mutationFn: (input: CreatePostInput) => createPost(communityId, input),
    onMutate: () => setComposerError(null),
    onSuccess: () => void invalidate(),
    onError: (error) => setComposerError(messageFrom(error)),
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
    onSuccess: () => setReporting(null),
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
      void invalidate();
    },
    onError: (error) => setEditError(messageFrom(error)),
  });

  const deleteMutation = useMutation({
    mutationFn: (post: Post) => deletePost(post.id, post.version),
    onSuccess: () => {
      setDeleting(null);
      void invalidate();
    },
  });

  if (communityQuery.isError) {
    return (
      <div className="mx-auto w-full max-w-3xl">
        <ErrorState
          title="Community not found"
          description="This community may have been archived."
          onRetry={() => void communityQuery.refetch()}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
      <Link
        href="/community?tab=groups"
        className="inline-flex items-center gap-1.5 text-caption font-medium text-text-secondary transition-colors hover:text-brand-primary"
      >
        <ArrowLeft className="size-4" aria-hidden /> Back to groups
      </Link>

      {communityQuery.isPending || community === undefined ? (
        <Skeleton className="h-32 rounded-xl" />
      ) : (
        <header className="rounded-xl border border-border-subtle bg-bg-surface p-6">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-h3 text-text-primary">{community.name}</h1>
                {community.topic ? (
                  <Badge variant="neutral">{community.topic}</Badge>
                ) : null}
              </div>
              <p className="mt-1 max-w-2xl text-body text-text-secondary">
                {community.summary}
              </p>
              {community.description ? (
                <p className="mt-2 max-w-2xl whitespace-pre-wrap text-body text-text-muted">
                  {community.description}
                </p>
              ) : null}
            </div>
            <Button
              variant={community.is_member ? "ghost" : "primary"}
              isLoading={membershipMutation.isPending}
              onClick={() => membershipMutation.mutate()}
            >
              {community.is_member ? "Leave" : "Join"}
            </Button>
          </div>
        </header>
      )}

      {community?.is_member ? (
        <PostComposer
          communities={[community]}
          isSubmitting={createPostMutation.isPending}
          error={composerError}
          onSubmit={(_, input) => createPostMutation.mutate(input)}
        />
      ) : community !== undefined ? (
        <Alert variant="info" title="Join to post and comment">
          Members can share updates and take part in discussions here.
        </Alert>
      ) : null}

      {postsQuery.isPending ? (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-40 rounded-lg" />
          <Skeleton className="h-40 rounded-lg" />
        </div>
      ) : posts.length === 0 ? (
        <EmptyState
          icon={MessageSquare}
          title="No posts yet"
          description="Start the conversation by sharing the first post."
        />
      ) : (
        <div className="flex flex-col gap-3">
          {posts.map((post) => (
            <PostCard
              key={post.id}
              post={post}
              showCommunity={false}
              onReport={setReporting}
              onEdit={setEditing}
              onDelete={setDeleting}
              busy={editMutation.isPending || deleteMutation.isPending}
            />
          ))}
          {postsQuery.hasNextPage ? (
            <div className="flex justify-center">
              <Button
                variant="secondary"
                isLoading={postsQuery.isFetchingNextPage}
                onClick={() => void postsQuery.fetchNextPage()}
              >
                Load more
              </Button>
            </div>
          ) : null}
        </div>
      )}

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
