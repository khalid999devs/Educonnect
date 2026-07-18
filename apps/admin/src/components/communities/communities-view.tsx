"use client";

import {
  Alert,
  Badge,
  Button,
  Dialog,
  ErrorState,
  Input,
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
  createCommunity,
  listCommunities,
  setCommunityVisibility,
  updateCommunity,
  type AdminCommunity,
  type CommunityContent,
} from "@/lib/api/admin-communities";
import { ApiError } from "@/lib/api/http";
import { communityMgmtKeys } from "@/lib/query-keys";

export function CommunitiesView() {
  const [visibility, setVisibility] = useState("");
  const [editing, setEditing] = useState<AdminCommunity | "new" | null>(null);
  const [visibilityTarget, setVisibilityTarget] =
    useState<AdminCommunity | null>(null);

  const query = useInfiniteQuery({
    queryKey: communityMgmtKeys.list(visibility),
    queryFn: ({ pageParam }) =>
      listCommunities({
        visibility: visibility || undefined,
        cursor: pageParam,
      }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const communities = query.data?.pages.flatMap((page) => page.items) ?? [];

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Communities</h1>
        <p className="text-body text-text-secondary">
          Create and manage curated groups. Archiving a group hides it from
          members and is recorded in the audit log.
        </p>
      </header>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="w-48">
          <Select
            value={visibility}
            onChange={(event) => setVisibility(event.target.value)}
            aria-label="Filter by visibility"
          >
            <option value="">All</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </Select>
        </div>
        <Button onClick={() => setEditing("new")}>New community</Button>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load communities"
          description="The community list could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : communities.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No communities yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {communities.map((community) => (
            <li
              key={community.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border-subtle bg-bg-surface px-4 py-3"
            >
              <div className="min-w-0">
                <span className="flex items-center gap-2">
                  <span className="font-medium text-text-primary">
                    {community.name}
                  </span>
                  <Badge
                    variant={
                      community.visibility === "published"
                        ? "success"
                        : "neutral"
                    }
                  >
                    {community.visibility}
                  </Badge>
                  {community.is_seeded ? <Badge>Seeded</Badge> : null}
                </span>
                <span className="text-caption text-text-muted">
                  {community.member_count} member(s) · {community.slug}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => setEditing(community)}
                >
                  Edit
                </Button>
                <Button
                  size="sm"
                  variant={
                    community.visibility === "published"
                      ? "destructive"
                      : "secondary"
                  }
                  onClick={() => setVisibilityTarget(community)}
                >
                  {community.visibility === "published" ? "Archive" : "Publish"}
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

      {editing ? (
        <CommunityForm
          community={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}

      {visibilityTarget ? (
        <VisibilityDialog
          community={visibilityTarget}
          onClose={() => setVisibilityTarget(null)}
        />
      ) : null}
    </div>
  );
}

function useRefresh() {
  const queryClient = useQueryClient();

  return () =>
    void queryClient.invalidateQueries({ queryKey: communityMgmtKeys.all });
}

function CommunityForm({
  community,
  onClose,
}: {
  community: AdminCommunity | null;
  onClose: () => void;
}) {
  const refresh = useRefresh();
  const [content, setContent] = useState<CommunityContent>(() => ({
    name: community?.name ?? "",
    summary: community?.summary ?? "",
    description: community?.description ?? null,
    topic: community?.topic ?? null,
  }));

  const mutation = useMutation({
    mutationFn: () =>
      community
        ? updateCommunity(community.id, content, community.version)
        : createCommunity(content),
    onSuccess: () => {
      refresh();
      onClose();
    },
  });

  const set = (field: keyof CommunityContent, value: string) =>
    setContent((prev) => ({ ...prev, [field]: value || null }));

  return (
    <Dialog
      open
      title={community ? "Edit community" : "New community"}
      onClose={onClose}
    >
      <div className="space-y-4">
        {mutation.isError ? (
          <Alert variant="error" title="Could not save">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}
        <Field label="Name">
          <Input
            value={content.name}
            onChange={(event) => set("name", event.target.value)}
          />
        </Field>
        <Field label="Summary">
          <Textarea
            rows={2}
            value={content.summary}
            onChange={(event) => set("summary", event.target.value)}
          />
        </Field>
        <Field label="Description (optional)">
          <Textarea
            rows={3}
            value={content.description ?? ""}
            onChange={(event) => set("description", event.target.value)}
          />
        </Field>
        <Field label="Topic (optional)">
          <Input
            value={content.topic ?? ""}
            onChange={(event) => set("topic", event.target.value)}
          />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            disabled={
              content.name.trim().length === 0 ||
              content.summary.trim().length === 0
            }
            onClick={() => mutation.mutate()}
          >
            {community ? "Save" : "Create"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

function VisibilityDialog({
  community,
  onClose,
}: {
  community: AdminCommunity;
  onClose: () => void;
}) {
  const refresh = useRefresh();
  const [reason, setReason] = useState("");
  const next = community.visibility === "published" ? "archived" : "published";

  const mutation = useMutation({
    mutationFn: () =>
      setCommunityVisibility({
        id: community.id,
        visibility: next,
        expectedVersion: community.version,
        reason: reason.trim(),
      }),
    onSuccess: () => {
      refresh();
      onClose();
    },
  });

  return (
    <Dialog
      open
      title={next === "archived" ? "Archive community" : "Publish community"}
      onClose={onClose}
    >
      <div className="space-y-4">
        <p className="text-body text-text-secondary">
          {next === "archived"
            ? `Archiving ${community.name} hides it from members. This is recorded in the audit log.`
            : `Publishing ${community.name} makes it visible to members again.`}
        </p>
        {mutation.isError ? (
          <Alert variant="error" title="Could not update">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}
        <Field label="Reason (recorded in the audit log)">
          <Textarea
            rows={2}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
        </Field>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant={next === "archived" ? "destructive" : "primary"}
            isLoading={mutation.isPending}
            disabled={reason.trim().length === 0}
            onClick={() => mutation.mutate()}
          >
            {next === "archived" ? "Archive" : "Publish"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-caption font-medium text-text-secondary">
        {label}
      </span>
      {children}
    </label>
  );
}
