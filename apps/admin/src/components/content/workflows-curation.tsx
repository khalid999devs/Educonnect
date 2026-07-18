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
  createWorkflow,
  DESTINATION_ACTIONS,
  listCategories,
  listWorkflows,
  updateWorkflow,
  type AdminWorkflow,
  type WorkflowContent,
  type WorkflowStepContent,
} from "@/lib/api/admin-content";
import { ApiError } from "@/lib/api/http";
import { formatDateTime, humanizeKey } from "@/lib/format";
import { contentKeys } from "@/lib/query-keys";

import { LifecycleControls, StateBadge } from "./lifecycle-controls";

export function WorkflowsCuration() {
  const [state, setState] = useState("");
  const [editing, setEditing] = useState<AdminWorkflow | "new" | null>(null);

  const query = useInfiniteQuery({
    queryKey: contentKeys.list("workflows", state),
    queryFn: ({ pageParam }) =>
      listWorkflows({ state: state || undefined, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const workflows = query.data?.pages.flatMap((page) => page.items) ?? [];

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
        <Button onClick={() => setEditing("new")}>New workflow</Button>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load workflows"
          description="The workflow catalog could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : workflows.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No workflows in this state yet.
        </p>
      ) : (
        <ul className="space-y-3">
          {workflows.map((workflow) => (
            <li
              key={workflow.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border-subtle bg-bg-surface px-4 py-3"
            >
              <div className="min-w-0">
                <span className="flex items-center gap-2">
                  <span className="font-medium text-text-primary">
                    {workflow.title}
                  </span>
                  <StateBadge state={workflow.state} />
                </span>
                <span className="text-caption text-text-muted">
                  {workflow.steps.length} step(s) · updated{" "}
                  {formatDateTime(workflow.updated_at)}
                </span>
              </div>
              <div className="flex items-center gap-2">
                {workflow.state === "draft" ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setEditing(workflow)}
                  >
                    Edit
                  </Button>
                ) : null}
                <LifecycleControls
                  type="workflows"
                  id={workflow.id}
                  state={workflow.state}
                  version={workflow.version}
                  invalidateKey={contentKeys.type("workflows")}
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
        <WorkflowForm
          workflow={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </div>
  );
}

function WorkflowForm({
  workflow,
  onClose,
}: {
  workflow: AdminWorkflow | null;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const categories = useQuery({
    queryKey: contentKeys.categories(),
    queryFn: listCategories,
  });

  const [content, setContent] = useState<Omit<WorkflowContent, "steps">>(
    () => ({
      category_slug: workflow?.category?.slug ?? "",
      title: workflow?.title ?? "",
      goal: workflow?.goal ?? "",
      expected_outcome: workflow?.expected_outcome ?? "",
      integrity_note: workflow?.integrity_note ?? "",
      provenance: workflow?.provenance ?? "",
    }),
  );
  const [steps, setSteps] = useState<WorkflowStepContent[]>(() =>
    workflow
      ? workflow.steps.map((step) => ({
          title: step.title,
          instruction: step.instruction,
          destination_action: step.destination_action,
        }))
      : [{ title: "", instruction: "", destination_action: null }],
  );

  const mutation = useMutation({
    mutationFn: () => {
      const payload: WorkflowContent = { ...content, steps };

      return workflow
        ? updateWorkflow(workflow.id, payload, workflow.version)
        : createWorkflow(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: contentKeys.type("workflows"),
      });
      onClose();
    },
  });

  const set = (field: keyof typeof content, value: string) =>
    setContent((prev) => ({ ...prev, [field]: value }));

  const setStep = (index: number, patch: Partial<WorkflowStepContent>) =>
    setSteps((prev) =>
      prev.map((step, i) => (i === index ? { ...step, ...patch } : step)),
    );

  return (
    <Dialog
      open
      size="lg"
      title={workflow ? "Edit workflow" : "New workflow"}
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
        <Field label="Goal">
          <Textarea
            rows={2}
            value={content.goal}
            onChange={(event) => set("goal", event.target.value)}
          />
        </Field>
        <Field label="Expected outcome">
          <Textarea
            rows={2}
            value={content.expected_outcome}
            onChange={(event) => set("expected_outcome", event.target.value)}
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

        <div className="space-y-3">
          <p className="text-caption font-medium uppercase tracking-wide text-text-muted">
            Steps
          </p>
          {steps.map((step, index) => (
            <div
              key={index}
              className="space-y-2 rounded-md border border-border-subtle p-3"
            >
              <div className="flex items-center justify-between">
                <span className="text-caption font-medium text-text-secondary">
                  Step {index + 1}
                </span>
                {steps.length > 1 ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                      setSteps((prev) => prev.filter((_, i) => i !== index))
                    }
                  >
                    Remove
                  </Button>
                ) : null}
              </div>
              <Input
                value={step.title}
                placeholder="Step title"
                onChange={(event) =>
                  setStep(index, { title: event.target.value })
                }
              />
              <Textarea
                rows={2}
                value={step.instruction}
                placeholder="Instruction"
                onChange={(event) =>
                  setStep(index, { instruction: event.target.value })
                }
              />
              <Select
                value={step.destination_action ?? ""}
                onChange={(event) =>
                  setStep(index, {
                    destination_action: event.target.value || null,
                  })
                }
              >
                <option value="">No destination action</option>
                {DESTINATION_ACTIONS.map((action) => (
                  <option key={action} value={action}>
                    {humanizeKey(action)}
                  </option>
                ))}
              </Select>
            </div>
          ))}
          <Button
            size="sm"
            variant="secondary"
            onClick={() =>
              setSteps((prev) => [
                ...prev,
                { title: "", instruction: "", destination_action: null },
              ])
            }
          >
            Add step
          </Button>
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {workflow ? "Save draft" : "Create draft"}
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
