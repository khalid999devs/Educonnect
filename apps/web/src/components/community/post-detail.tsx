"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  EmptyState,
  ErrorState,
  FormField,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { ArrowLeft, BadgeCheck, Flag, Trash2 } from "lucide-react";
import Link from "next/link";
import { useMemo, useState } from "react";

import {
  createComment,
  deleteComment,
  deletePost,
  getCommunity,
  getPost,
  listComments,
  reportComment,
  reportPost,
  updatePost,
  type Comment,
  type Post,
  type ReportReason,
} from "@/lib/api/community";
import { ApiError } from "@/lib/api/http";
import { communityKeys } from "@/lib/query-keys";
import { formatRelativeTime } from "@/lib/format";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { PostCard } from "./post-card";
import { PostEditDialog } from "./post-edit-dialog";
import { ReportDialog } from "./report-dialog";

type ReportTarget = { kind: "post" | "comment"; id: string };

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

export function PostDetail({ postId }: { postId: string }) {
  const queryClient = useQueryClient();
  const [commentBody, setCommentBody] = useState("");
  const [commentError, setCommentError] = useState<string | null>(null);
  const [reportTarget, setReportTarget] = useState<ReportTarget | null>(null);
  const [reportError, setReportError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Post | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [deletingPost, setDeletingPost] = useState<Post | null>(null);
  const [deletingComment, setDeletingComment] = useState<Comment | null>(null);

  const postQuery = useQuery({
    queryKey: communityKeys.post(postId),
    queryFn: () => getPost(postId),
  });
  const post = postQuery.data;

  const communityQuery = useQuery({
    queryKey: post?.community
      ? communityKeys.community(post.community.id)
      : ["community", "community", "unknown"],
    queryFn: () => getCommunity(post!.community!.id),
    enabled: post?.community !== undefined,
  });
  const isMember = communityQuery.data?.is_member ?? false;

  const commentsQuery = useInfiniteQuery({
    queryKey: communityKeys.comments(postId),
    queryFn: ({ pageParam }) => listComments(postId, { cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
    enabled: post !== undefined,
  });
  const comments = useMemo(
    () => (commentsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [commentsQuery.data],
  );

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: communityKeys.all });

  const commentMutation = useMutation({
    mutationFn: (body: string) => createComment(postId, body),
    onMutate: () => setCommentError(null),
    onSuccess: () => {
      setCommentBody("");
      void invalidate();
    },
    onError: (error) => setCommentError(messageFrom(error)),
  });

  const reportMutation = useMutation({
    mutationFn: ({
      target,
      reason,
      detail,
    }: {
      target: ReportTarget;
      reason: ReportReason;
      detail: string | null;
    }) =>
      target.kind === "post"
        ? reportPost(target.id, { reason, detail })
        : reportComment(target.id, { reason, detail }),
    onMutate: () => setReportError(null),
    onSuccess: () => setReportTarget(null),
    onError: (error) => setReportError(messageFrom(error)),
  });

  const editMutation = useMutation({
    mutationFn: ({
      target,
      input,
    }: {
      target: Post;
      input: { title: string | null; body: string };
    }) => updatePost(target.id, { ...input, expected_version: target.version }),
    onMutate: () => setEditError(null),
    onSuccess: () => {
      setEditing(null);
      void invalidate();
    },
    onError: (error) => setEditError(messageFrom(error)),
  });

  const deletePostMutation = useMutation({
    mutationFn: (target: Post) => deletePost(target.id, target.version),
    onSuccess: () => {
      setDeletingPost(null);
      void invalidate();
    },
  });

  const deleteCommentMutation = useMutation({
    mutationFn: (target: Comment) => deleteComment(target.id, target.version),
    onSuccess: () => {
      setDeletingComment(null);
      void invalidate();
    },
  });

  if (postQuery.isError) {
    return (
      <div className="mx-auto w-full max-w-3xl">
        <ErrorState
          title="Post not found"
          description="This post may have been removed."
          onRetry={() => void postQuery.refetch()}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-5">
      <Link
        href="/community"
        className="inline-flex items-center gap-1.5 text-caption font-medium text-text-secondary hover:text-brand-primary"
      >
        <ArrowLeft className="size-4" aria-hidden /> Back to community
      </Link>

      {postQuery.isPending || post === undefined ? (
        <Skeleton className="h-48 rounded-lg" />
      ) : (
        <PostCard
          post={post}
          onReport={() => setReportTarget({ kind: "post", id: post.id })}
          onEdit={setEditing}
          onDelete={setDeletingPost}
          busy={editMutation.isPending || deletePostMutation.isPending}
        />
      )}

      <section className="flex flex-col gap-3">
        <h2 className="text-h4 text-text-primary">
          {post?.comment_count ?? 0}{" "}
          {(post?.comment_count ?? 0) === 1 ? "comment" : "comments"}
        </h2>

        {post !== undefined && post.moderation_state === "visible" ? (
          isMember ? (
            <Card>
              <CardContent className="flex flex-col gap-3 p-4">
                {commentError ? (
                  <Alert variant="error" title="Could not comment">
                    {commentError}
                  </Alert>
                ) : null}
                <FormField id="comment-body" label="Add a comment" required>
                  {(control) => (
                    <Textarea
                      {...control}
                      value={commentBody}
                      rows={2}
                      maxLength={2000}
                      onChange={(event) => setCommentBody(event.target.value)}
                      placeholder="Share your thoughts…"
                    />
                  )}
                </FormField>
                <div className="flex justify-end">
                  <Button
                    variant="primary"
                    isLoading={commentMutation.isPending}
                    loadingLabel="Posting"
                    disabled={commentBody.trim().length === 0}
                    onClick={() => commentMutation.mutate(commentBody.trim())}
                  >
                    Comment
                  </Button>
                </div>
              </CardContent>
            </Card>
          ) : post.community ? (
            <Alert variant="info" title="Join to comment">
              <Link
                href={`/community/groups/${post.community.id}`}
                className="font-medium text-brand-primary hover:underline"
              >
                Join {post.community.name}
              </Link>{" "}
              to take part in this discussion.
            </Alert>
          ) : null
        ) : null}

        {commentsQuery.isPending ? (
          <Skeleton className="h-20 rounded-lg" />
        ) : comments.length === 0 ? (
          <EmptyState
            title="No comments yet"
            description="Be the first to reply."
          />
        ) : (
          <ul className="flex flex-col gap-2">
            {comments.map((comment) => (
              <li
                key={comment.id}
                className="rounded-lg border border-border-subtle bg-bg-surface p-4"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="flex items-center gap-2 text-body font-medium text-text-primary">
                    {comment.author.name}
                    {comment.author.is_verified_mentor ? (
                      <Badge variant="brand">
                        <BadgeCheck className="size-3.5" aria-hidden />
                        Mentor
                      </Badge>
                    ) : null}
                    <span className="text-caption font-normal text-text-muted">
                      {formatRelativeTime(comment.created_at)}
                    </span>
                  </span>
                  {comment.is_mine ? (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setDeletingComment(comment)}
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </Button>
                  ) : (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        setReportTarget({ kind: "comment", id: comment.id })
                      }
                    >
                      <Flag className="size-4" aria-hidden />
                    </Button>
                  )}
                </div>
                <p className="mt-1 whitespace-pre-wrap break-words text-body text-text-secondary">
                  {comment.body}
                </p>
              </li>
            ))}
            {commentsQuery.hasNextPage ? (
              <Button
                variant="secondary"
                isLoading={commentsQuery.isFetchingNextPage}
                onClick={() => void commentsQuery.fetchNextPage()}
              >
                Load more
              </Button>
            ) : null}
          </ul>
        )}
      </section>

      <ReportDialog
        open={reportTarget !== null}
        subjectLabel={reportTarget?.kind ?? "post"}
        isSubmitting={reportMutation.isPending}
        error={reportError}
        onClose={() => setReportTarget(null)}
        onSubmit={(reason, detail) => {
          if (reportTarget) {
            reportMutation.mutate({ target: reportTarget, reason, detail });
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
            editMutation.mutate({ target: editing, input });
          }
        }}
      />
      <ConfirmDialog
        open={deletingPost !== null}
        title="Delete this post?"
        description="Your post will be removed from the community feed. This cannot be undone."
        confirmLabel="Delete post"
        busy={deletePostMutation.isPending}
        error={
          deletePostMutation.isError
            ? messageFrom(deletePostMutation.error)
            : null
        }
        onConfirm={() => {
          if (deletingPost) {
            deletePostMutation.mutate(deletingPost);
          }
        }}
        onCancel={() => setDeletingPost(null)}
      />
      <ConfirmDialog
        open={deletingComment !== null}
        title="Delete this comment?"
        description="Your comment will be removed. This cannot be undone."
        confirmLabel="Delete comment"
        busy={deleteCommentMutation.isPending}
        error={
          deleteCommentMutation.isError
            ? messageFrom(deleteCommentMutation.error)
            : null
        }
        onConfirm={() => {
          if (deletingComment) {
            deleteCommentMutation.mutate(deletingComment);
          }
        }}
        onCancel={() => setDeletingComment(null)}
      />
    </div>
  );
}
