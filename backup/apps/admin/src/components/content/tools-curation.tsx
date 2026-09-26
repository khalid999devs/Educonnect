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
  createTool,
  listCategories,
  listTools,
  updateTool,
  type AdminTool,
  type ToolContent,
} from "@/lib/api/admin-content";
import { ApiError } from "@/lib/api/http";
import { formatDateTime } from "@/lib/format";
import { contentKeys } from "@/lib/query-keys";

import { LifecycleControls, StateBadge } from "./lifecycle-controls";

function emptyContent(): ToolContent {
  return {
    category_slug: "",
    name: "",
    purpose: "",
    selection_reason: "",
    use_cases: [],
    usage_guidance: "",
    limitations: "",
    cost_note: "",
    privacy_note: "",
    url: "",
    provenance: "",
  };
}

export function ToolsCuration() {
  const [state, setState] = useState("");
  const [editing, setEditing] = useState<AdminTool | "new" | null>(null);

  const query = useInfiniteQuery({
    queryKey: contentKeys.list("tools", state),
    queryFn: ({ pageParam }) =>
      listTools({ state: state || undefined, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const tools = query.data?.pages.flatMap((page) => page.items) ?? [];

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
        <Button onClick={() => setEditing("new")}>New tool</Button>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load tools"
          description="The tool catalog could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : tools.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No tools in this state yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {tools.map((tool) => (
            <li
              key={tool.id}
              className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-3"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                  <span className="flex items-center gap-2">
                    <span className="font-medium text-text-primary">
                      {tool.name}
                    </span>
                    <StateBadge state={tool.state} />
                  </span>
                  <span className="text-caption text-text-muted">
                    {tool.category?.name ?? "Uncategorized"} · updated{" "}
                    {formatDateTime(tool.updated_at)}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  {tool.state === "draft" ? (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => setEditing(tool)}
                    >
                      Edit
                    </Button>
                  ) : null}
                  <LifecycleControls
                    type="tools"
                    id={tool.id}
                    state={tool.state}
                    version={tool.version}
                    invalidateKey={contentKeys.type("tools")}
                  />
                </div>
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
        <ToolForm
          tool={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </div>
  );
}

function ToolForm({
  tool,
  onClose,
}: {
  tool: AdminTool | null;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const categories = useQuery({
    queryKey: contentKeys.categories(),
    queryFn: listCategories,
  });

  const [content, setContent] = useState<ToolContent>(() =>
    tool
      ? {
          category_slug: tool.category?.slug ?? "",
          name: tool.name,
          purpose: tool.purpose,
          selection_reason: tool.selection_reason,
          use_cases: tool.use_cases,
          usage_guidance: tool.usage_guidance,
          limitations: tool.limitations,
          cost_note: tool.cost_note,
          privacy_note: tool.privacy_note,
          url: tool.url,
          provenance: tool.provenance,
        }
      : emptyContent(),
  );
  const [useCasesText, setUseCasesText] = useState(
    (tool?.use_cases ?? []).join("\n"),
  );

  const mutation = useMutation({
    mutationFn: () => {
      const payload: ToolContent = {
        ...content,
        use_cases: useCasesText
          .split("\n")
          .map((line) => line.trim())
          .filter((line) => line.length > 0),
      };

      return tool
        ? updateTool(tool.id, payload, tool.version)
        : createTool(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: contentKeys.type("tools"),
      });
      onClose();
    },
  });

  const set = (field: keyof ToolContent, value: string) =>
    setContent((prev) => ({ ...prev, [field]: value }));

  return (
    <Dialog
      open
      size="lg"
      title={tool ? "Edit tool" : "New tool"}
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
        <Field label="Name">
          <Input
            value={content.name}
            onChange={(event) => set("name", event.target.value)}
          />
        </Field>
        <Field label="Purpose">
          <Textarea
            rows={2}
            value={content.purpose}
            onChange={(event) => set("purpose", event.target.value)}
          />
        </Field>
        <Field label="Why it fits">
          <Textarea
            rows={2}
            value={content.selection_reason}
            onChange={(event) => set("selection_reason", event.target.value)}
          />
        </Field>
        <Field label="Use cases (one per line)">
          <Textarea
            rows={3}
            value={useCasesText}
            onChange={(event) => setUseCasesText(event.target.value)}
          />
        </Field>
        <Field label="Usage guidance">
          <Textarea
            rows={2}
            value={content.usage_guidance}
            onChange={(event) => set("usage_guidance", event.target.value)}
          />
        </Field>
        <Field label="Limitations">
          <Textarea
            rows={2}
            value={content.limitations}
            onChange={(event) => set("limitations", event.target.value)}
          />
        </Field>
        <Field label="Cost note">
          <Textarea
            rows={2}
            value={content.cost_note}
            onChange={(event) => set("cost_note", event.target.value)}
          />
        </Field>
        <Field label="Privacy note">
          <Textarea
            rows={2}
            value={content.privacy_note}
            onChange={(event) => set("privacy_note", event.target.value)}
          />
        </Field>
        <Field label="External URL (https)">
          <Input
            value={content.url}
            onChange={(event) => set("url", event.target.value)}
          />
        </Field>
        <Field label="Provenance">
          <Textarea
            rows={2}
            value={content.provenance}
            onChange={(event) => set("provenance", event.target.value)}
          />
        </Field>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {tool ? "Save draft" : "Create draft"}
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
