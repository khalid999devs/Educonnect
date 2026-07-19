"use client";

import {
  Alert,
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
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { useState } from "react";

import {
  createPrompt,
  listCategories,
  listPrompts,
  listTools,
  updatePrompt,
  type AdminPrompt,
  type PromptContent,
} from "@/lib/api/admin-content";
import { ApiError } from "@/lib/api/http";
import { formatDateTime } from "@/lib/format";
import { contentKeys } from "@/lib/query-keys";

import { LifecycleControls, StateBadge } from "./lifecycle-controls";

export function PromptsCuration() {
  const [state, setState] = useState("");
  const [editing, setEditing] = useState<AdminPrompt | "new" | null>(null);

  const query = useInfiniteQuery({
    queryKey: contentKeys.list("prompts", state),
    queryFn: ({ pageParam }) =>
      listPrompts({ state: state || undefined, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const prompts = query.data?.pages.flatMap((page) => page.items) ?? [];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="w-48">
          <Select
            value={state}
            onChange={(event) => setState(event.target.value)}
            aria-label="Filter by state"
          >
            <option value="">All states</option>
            <option value="draft">Draft</option>
            <option value="in_review">In review</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </Select>
        </div>
        <Button onClick={() => setEditing("new")}>New prompt</Button>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load prompts"
          description="The prompt catalog could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : prompts.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No prompts in this state yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {prompts.map((prompt) => (
            <li
              key={prompt.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border-subtle bg-bg-surface px-4 py-3"
            >
              <div className="min-w-0">
                <span className="flex items-center gap-2">
                  <span className="font-medium text-text-primary">
                    {prompt.title}
                  </span>
                  <StateBadge state={prompt.state} />
                </span>
                <span className="text-caption text-text-muted">
                  {prompt.related_tools.length} related tool(s) · updated{" "}
                  {formatDateTime(prompt.updated_at)}
                </span>
              </div>
              <div className="flex items-center gap-2">
                {prompt.state === "draft" ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setEditing(prompt)}
                  >
                    Edit
                  </Button>
                ) : null}
                <LifecycleControls
                  type="prompts"
                  id={prompt.id}
                  state={prompt.state}
                  version={prompt.version}
                  invalidateKey={contentKeys.type("prompts")}
                />
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
        <PromptForm
          prompt={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </div>
  );
}

function PromptForm({
  prompt,
  onClose,
}: {
  prompt: AdminPrompt | null;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const categories = useQuery({
    queryKey: contentKeys.categories(),
    queryFn: listCategories,
  });
  const publishedTools = useQuery({
    queryKey: [...contentKeys.type("tools"), "published-for-prompts"],
    queryFn: () => listTools({ state: "published" }),
  });

  const [content, setContent] = useState<Omit<PromptContent, "placeholders">>(
    () => ({
      category_slug: prompt?.category?.slug ?? "",
      title: prompt?.title ?? "",
      purpose: prompt?.purpose ?? "",
      template_body: prompt?.template_body ?? "",
      expected_output: prompt?.expected_output ?? "",
      integrity_note: prompt?.integrity_note ?? "",
      provenance: prompt?.provenance ?? "",
      related_tools: prompt?.related_tools.map((tool) => tool.id) ?? [],
    }),
  );
  const [placeholdersText, setPlaceholdersText] = useState(
    (prompt?.placeholders ?? []).join(", "),
  );

  const mutation = useMutation({
    mutationFn: () => {
      const payload: PromptContent = {
        ...content,
        placeholders: placeholdersText
          .split(",")
          .map((value) => value.trim())
          .filter((value) => value.length > 0),
      };

      return prompt
        ? updatePrompt(prompt.id, payload, prompt.version)
        : createPrompt(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: contentKeys.type("prompts"),
      });
      onClose();
    },
  });

  const set = (field: keyof typeof content, value: string) =>
    setContent((prev) => ({ ...prev, [field]: value }));

  const toggleTool = (id: string) =>
    setContent((prev) => ({
      ...prev,
      related_tools: prev.related_tools.includes(id)
        ? prev.related_tools.filter((toolId) => toolId !== id)
        : [...prev.related_tools, id],
    }));

  const tools = publishedTools.data?.items ?? [];

  return (
    <Dialog
      open
      size="lg"
      title={prompt ? "Edit prompt" : "New prompt"}
      onClose={onClose}
    >
      <div className="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
        {mutation.isError ? (
          <Alert variant="error" title="Could not save">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}

        <Field label="Category">
          <Select
            value={content.category_slug}
            onChange={(event) => set("category_slug", event.target.value)}
          >
            <option value="">Select a category…</option>
            {(categories.data ?? []).map((category) => (
              <option key={category.slug} value={category.slug}>
                {category.name}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Title">
          <Input
            value={content.title}
            onChange={(event) => set("title", event.target.value)}
          />
        </Field>
        <Field label="Purpose">
          <Textarea
            rows={2}
            value={content.purpose}
            onChange={(event) => set("purpose", event.target.value)}
          />
        </Field>
        <Field label="Template body (use {{placeholders}})">
          <Textarea
            rows={4}
            value={content.template_body}
            onChange={(event) => set("template_body", event.target.value)}
          />
        </Field>
        <Field label="Placeholders (comma-separated)">
          <Input
            value={placeholdersText}
            onChange={(event) => setPlaceholdersText(event.target.value)}
          />
        </Field>
        <Field label="Expected output">
          <Textarea
            rows={2}
            value={content.expected_output}
            onChange={(event) => set("expected_output", event.target.value)}
          />
        </Field>
        <Field label="Integrity note">
          <Textarea
            rows={2}
            value={content.integrity_note}
            onChange={(event) => set("integrity_note", event.target.value)}
          />
        </Field>
        <Field label="Provenance">
          <Textarea
            rows={2}
            value={content.provenance}
            onChange={(event) => set("provenance", event.target.value)}
          />
        </Field>
        <Field label="Related published tools (required before publishing)">
          {tools.length === 0 ? (
            <p className="text-caption text-text-muted">
              No published tools yet. Publish a tool first to relate it.
            </p>
          ) : (
            <div className="space-y-1">
              {tools.map((tool) => (
                <label
                  key={tool.id}
                  className="flex items-center gap-2 text-body"
                >
                  <input
                    type="checkbox"
                    checked={content.related_tools.includes(tool.id)}
                    onChange={() => toggleTool(tool.id)}
                    className="size-4 accent-brand-primary"
                  />
                  {tool.name}
                </label>
              ))}
            </div>
          )}
        </Field>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {prompt ? "Save draft" : "Create draft"}
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
