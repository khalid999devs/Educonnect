"use client";

import { Badge, Button, Card, CardContent } from "@educonnect/ui";
import {
  BadgeCheck,
  ExternalLink,
  Flag,
  MessageSquare,
  Pencil,
  Trash2,
} from "lucide-react";
import Link from "next/link";

import { formatRelativeTime } from "@/lib/format";
import type { Post } from "@/lib/api/community";

const REMOVED_LABEL: Record<string, string> = {
  hidden_by_moderator: "This post was hidden by a moderator.",
  removed_by_author: "This post was removed by its author.",
};

export function PostCard({
  post,
  showCommunity = true,
  onReport,
  onEdit,
  onDelete,
  busy = false,
}: {
  post: Post;
  showCommunity?: boolean;
  onReport: (post: Post) => void;
  onEdit?: (post: Post) => void;
  onDelete?: (post: Post) => void;
  busy?: boolean;
}) {
  const isVisible = post.moderation_state === "visible";
  const initial = post.author.name.trim().charAt(0).toUpperCase() || "?";

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-5">
        <div className="flex items-start gap-3">
          <span
            aria-hidden
            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-bg-interactive text-label font-semibold text-text-secondary"
          >
            {initial}
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span className="font-semibold text-text-primary">
                {post.author.name}
              </span>
              {post.author.is_verified_mentor ? (
                <Badge variant="brand">
                  <BadgeCheck className="size-3.5" aria-hidden />
                  Verified mentor
                </Badge>
              ) : null}
            </div>
            <p className="text-caption text-text-muted">
              {showCommunity && post.community ? (
                <>
                  <Link
                    href={`/community/groups/${post.community.id}`}
                    className="font-medium text-text-secondary hover:text-brand-primary"
                  >
                    {post.community.name}
                  </Link>
                  {" · "}
                </>
              ) : null}
              <time dateTime={post.created_at}>
                {formatRelativeTime(post.created_at)}
              </time>
            </p>
          </div>
        </div>

        {isVisible ? (
          <div className="flex flex-col gap-2">
            {post.title ? (
              <h3 className="text-body-lg font-semibold text-text-primary">
                {post.title}
              </h3>
            ) : null}
            {post.body ? (
              <p className="whitespace-pre-wrap wrap-break-word text-body text-text-secondary">
                {post.body}
              </p>
            ) : null}
            {post.shared_resource ? (
              <a
                href={post.shared_resource.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-2 rounded-lg border border-border-subtle bg-bg-subtle px-3 py-2 text-body text-text-secondary transition-colors hover:border-border-strong"
              >
                <ExternalLink
                  className="size-4 shrink-0 text-brand-primary"
                  aria-hidden
                />
                <span className="truncate">{post.shared_resource.title}</span>
              </a>
            ) : null}
          </div>
        ) : (
          <p className="rounded-lg border border-border-subtle bg-bg-subtle px-3 py-3 text-body text-text-muted">
            {REMOVED_LABEL[post.moderation_state] ??
              "This post is unavailable."}
          </p>
        )}

        <div className="flex flex-wrap items-center gap-2 pt-1">
          <Link
            href={`/community/posts/${post.id}`}
            className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-caption font-medium text-text-secondary transition-colors hover:bg-bg-interactive"
          >
            <MessageSquare className="size-4" aria-hidden />
            {post.comment_count}{" "}
            {post.comment_count === 1 ? "comment" : "comments"}
          </Link>
          <div className="ml-auto flex items-center gap-1">
            {isVisible && post.is_mine && onEdit ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onEdit(post)}
                disabled={busy}
              >
                <Pencil className="size-4" aria-hidden />
                Edit
              </Button>
            ) : null}
            {isVisible && post.is_mine && onDelete ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onDelete(post)}
                disabled={busy}
              >
                <Trash2 className="size-4" aria-hidden />
                Delete
              </Button>
            ) : null}
            {isVisible && !post.is_mine ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onReport(post)}
                disabled={busy}
              >
                <Flag className="size-4" aria-hidden />
                Report
              </Button>
            ) : null}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
