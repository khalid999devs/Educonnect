"use client";

import {
  Alert,
  Button,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { MessagesSquare } from "lucide-react";
import { useMemo, useState } from "react";

import {
  createPost,
  deletePost,
  listFeed,
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
import { useCommunities } from "./use-communities";

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

/** The membership-scoped feed: the default Community tab. */
export function PostsTab() {
  const queryClient = useQueryClient();
  const [composerError, setComposerError] = useState<string | null>(null);
  const [reporting, setReporting] = useState<Post | null>(null);
  const [reportError, setReportError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Post | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<Post | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const { joined } = useCommunities({ loadAll: true });

  const feedQuery = useInfiniteQuery({
    queryKey: communityKeys.feed(),
    queryFn: ({ pageParam }) => listFeed({ perPage: 20, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });
  const feedPosts = useMemo(
    () => (feedQuery.data?.pages ?? []).flatMap((page) => page.data),
    [feedQuery.data],
  );

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
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
      {notice ? <Alert variant="success" title={notice} /> : null}

      <div className="motion-safe:animate-fade-up">
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
          <Alert variant="info" title="Join a group to start posting">
            Open the Groups tab and join a space. Its posts then appear here.
          </Alert>
        )}
      </div>

      {feedQuery.isError ? (
        <ErrorState
          description="We couldn't load your feed."
          onRetry={() => void feedQuery.refetch()}
        />
      ) : feedQuery.isPending ? (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-40 rounded-lg" />
          <Skeleton className="h-40 rounded-lg" />
          <Skeleton className="h-40 rounded-lg" />
        </div>
      ) : feedPosts.length === 0 ? (
        <EmptyState
          icon={MessagesSquare}
          title={joined.length > 0 ? "No posts yet" : "Your feed is empty"}
          description={
            joined.length > 0
              ? "Be the first to post in one of your groups."
              : "Join a group to see posts from other students here."
          }
        />
      ) : (
        <div className="flex flex-col gap-3 motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]">
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
        description="Your post will be removed from the group feed. This cannot be undone."
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
