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
  createTemplate,
  listCategories,
  listTemplates,
  TEMPLATE_FORMATS,
  updateTemplate,
  type AdminTemplate,
  type TemplateContent,
} from "@/lib/api/admin-content";
import { ApiError } from "@/lib/api/http";
import { formatDateTime } from "@/lib/format";
import { contentKeys } from "@/lib/query-keys";

import { LifecycleControls, StateBadge } from "./lifecycle-controls";

export function TemplatesCuration() {
  const [state, setState] = useState("");
  const [editing, setEditing] = useState<AdminTemplate | "new" | null>(null);

  const query = useInfiniteQuery({
    queryKey: contentKeys.list("templates", state),
    queryFn: ({ pageParam }) =>
      listTemplates({ state: state || undefined, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const templates = query.data?.pages.flatMap((page) => page.items) ?? [];

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
        <Button onClick={() => setEditing("new")}>New template</Button>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load templates"
          description="The template catalog could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : templates.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No templates in this state yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {templates.map((template) => (
            <li
              key={template.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border-subtle bg-bg-surface px-4 py-3"
            >
              <div className="min-w-0">
                <span className="flex items-center gap-2">
                  <span className="font-medium text-text-primary">
                    {template.title}
                  </span>
                  <StateBadge state={template.state} />
                </span>
                <span className="text-caption text-text-muted">
                  v{template.latest_version?.number ?? "—"} · updated{" "}
                  {formatDateTime(template.updated_at)}
                </span>
              </div>
              <div className="flex items-center gap-2">
                {template.state === "draft" ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setEditing(template)}
                  >
                    Edit
                  </Button>
                ) : null}
                <LifecycleControls
                  type="templates"
                  id={template.id}
                  state={template.state}
                  version={template.version}
                  invalidateKey={contentKeys.type("templates")}
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
        <TemplateForm
          template={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </div>
  );
}

function TemplateForm({
  template,
  onClose,
}: {
  template: AdminTemplate | null;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const categories = useQuery({
    queryKey: contentKeys.categories(),
    queryFn: listCategories,
  });

  const [content, setContent] = useState<TemplateContent>(() => ({
    category_slug: template?.category?.slug ?? "",
    title: template?.title ?? "",
    summary: template?.summary ?? "",
    integrity_note: template?.integrity_note ?? "",
    provenance: template?.provenance ?? "",
    format: template?.latest_version?.format ?? "markdown",
    body: template?.latest_version?.body ?? "",
    change_note: null,
  }));

  const mutation = useMutation({
    mutationFn: () =>
      template
        ? updateTemplate(template.id, content, template.version)
        : createTemplate(content),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: contentKeys.type("templates"),
      });
      onClose();
    },
  });

  const set = (field: keyof TemplateContent, value: string) =>
    setContent((prev) => ({ ...prev, [field]: value }));

  return (
    <Dialog
      open
      size="lg"
      title={template ? "Edit template" : "New template"}
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
        <Field label="Summary">
          <Textarea
            rows={2}
            value={content.summary}
            onChange={(event) => set("summary", event.target.value)}
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
        <Field label="Body format">
          <Select
            value={content.format}
            onChange={(event) =>
              set("format", event.target.value as TemplateContent["format"])
            }
          >
            {TEMPLATE_FORMATS.map((format) => (
              <option key={format} value={format}>
                {format}
              </option>
            ))}
          </Select>
        </Field>
        <Field
          label={
            template
              ? "Body (saving a change adds a new version)"
              : "Body (the first version)"
          }
        >
          <Textarea
            rows={6}
            value={content.body}
            onChange={(event) => set("body", event.target.value)}
          />
        </Field>
        <Field label="Change note (optional)">
          <Input
            value={content.change_note ?? ""}
            onChange={(event) =>
              setContent((prev) => ({
                ...prev,
                change_note: event.target.value || null,
              }))
            }
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
            {template ? "Save draft" : "Create draft"}
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
