"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Select,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Inbox, Link2, Sparkles, UploadCloud } from "lucide-react";
import Image from "next/image";
import { useState } from "react";

import { listCourses } from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import {
  cancelIntakeItem,
  confirmIntake,
  createFileIntake,
  createLinkIntake,
  getIntakeItem,
  isProcessing,
  listIntakeItems,
  listIntakeSuggestions,
  retryIntakeItem,
  type ConfirmDecision,
  type IntakeItem,
} from "@/lib/api/intake";
import { listResources } from "@/lib/api/resources";
import { intakeKeys } from "@/lib/query-keys";
import { IntakeDetail } from "./intake-detail";
import { IntakeReview } from "./intake-review";

const STATE_DOT: Record<string, string> = {
  saved: "bg-status-success",
  awaiting_review: "bg-brand-primary",
  failed_final: "bg-status-error",
  failed_retryable: "bg-status-deadline",
  cancelled: "bg-text-muted",
};

export function IntakeView() {
  const queryClient = useQueryClient();
  const [mode, setMode] = useState<"link" | "file">("link");
  const [url, setUrl] = useState("");
  const [context, setContext] = useState("");
  const [resourceId, setResourceId] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const listQuery = useQuery({
    queryKey: intakeKeys.list({}),
    queryFn: () => listIntakeItems({ perPage: 30 }),
  });

  const readyFilesQuery = useQuery({
    queryKey: ["resources", "ready-files"],
    queryFn: () =>
      listResources({ kind: "file", fileStatus: "ready", perPage: 50 }),
    staleTime: 60_000,
  });

  const coursesQuery = useQuery({
    queryKey: ["courses", "list"],
    queryFn: () => listCourses({ status: "active", perPage: 50 }),
    staleTime: 5 * 60_000,
  });

  const selectedQuery = useQuery({
    queryKey: intakeKeys.item(selectedId ?? ""),
    queryFn: () => getIntakeItem(selectedId as string),
    enabled: selectedId !== null,
    refetchInterval: (query) => {
      const state = query.state.data?.state;

      return state && isProcessing(state) ? 2500 : false;
    },
  });

  const suggestionsQuery = useQuery({
    queryKey: intakeKeys.suggestions(selectedId ?? ""),
    queryFn: () => listIntakeSuggestions(selectedId as string),
    enabled:
      selectedId !== null && selectedQuery.data?.state === "awaiting_review",
  });

  const invalidateAll = () => {
    void queryClient.invalidateQueries({ queryKey: intakeKeys.all });
  };

  const onSubmitted = (item: IntakeItem) => {
    invalidateAll();
    setSelectedId(item.id);
    setUrl("");
    setContext("");
    setResourceId("");
    setNotice("Submitted — you'll see progress below.");
  };

  const linkMutation = useMutation({
    mutationFn: () => createLinkIntake(url.trim(), context.trim() || undefined),
    onSuccess: onSubmitted,
  });

  const fileMutation = useMutation({
    mutationFn: () => createFileIntake(resourceId, context.trim() || undefined),
    onSuccess: onSubmitted,
  });

  const actionMutation = useMutation({
    mutationFn: ({
      action,
      itemId,
    }: {
      action: "cancel" | "retry";
      itemId: string;
    }) =>
      action === "cancel" ? cancelIntakeItem(itemId) : retryIntakeItem(itemId),
    onSuccess: invalidateAll,
    onError: () => setNotice("That action couldn't be completed. Try again."),
  });

  const confirmMutation = useMutation({
    mutationFn: ({
      itemId,
      decisions,
    }: {
      itemId: string;
      decisions: ConfirmDecision[];
    }) => confirmIntake(itemId, decisions),
    onSuccess: () => {
      invalidateAll();
      setNotice("Saved — applied suggestions are now in your workspace.");
    },
  });

  const items = listQuery.data?.data ?? [];
  const selected = selectedQuery.data;
  const submitError =
    (mode === "link" ? linkMutation.error : fileMutation.error) ?? null;
  const submitApiError = submitError instanceof ApiError ? submitError : null;

  return (
    <div className="space-y-4">
      <header className="relative min-h-44 overflow-hidden rounded-xl border border-border-default lg:min-h-52">
        <Image
          src="/marketing/minimal-desk.jpg"
          alt=""
          fill
          priority
          sizes="(max-width: 1024px) 100vw, 1100px"
          className="object-cover object-center"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-r from-bg-canvas/95 via-bg-canvas/75 to-bg-canvas/25"
        />
        <div className="relative flex max-w-xl flex-col gap-3 p-6 lg:p-8">
          <h1 className="text-h2 text-text-primary">Smart Intake</h1>
          <p className="text-body-lg text-text-secondary">
            Drop a link or a file. We extract the text, suggest tasks and
            resources with reasons, and create nothing until you review and
            confirm.
          </p>
        </div>
      </header>

      {notice ? (
        <Alert variant="info" title="Intake">
          {notice}
        </Alert>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[22rem_minmax(0,1fr)] xl:items-start">
        <div className="space-y-4">
          {/* Submit panel */}
          <Card>
            <CardHeader className="flex flex-wrap items-center justify-between gap-2">
              <CardTitle className="flex items-center gap-2">
                <Sparkles
                  aria-hidden="true"
                  className="size-5 text-brand-primary"
                />
                New intake
              </CardTitle>
              <div
                role="group"
                aria-label="Source kind"
                className="flex items-center rounded-md border border-border-default p-0.5"
              >
                <Button
                  variant={mode === "link" ? "secondary" : "ghost"}
                  size="sm"
                  aria-pressed={mode === "link"}
                  onClick={() => setMode("link")}
                >
                  <Link2 aria-hidden="true" className="size-4" />
                  Link
                </Button>
                <Button
                  variant={mode === "file" ? "secondary" : "ghost"}
                  size="sm"
                  aria-pressed={mode === "file"}
                  onClick={() => setMode("file")}
                >
                  <UploadCloud aria-hidden="true" className="size-4" />
                  File
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {mode === "link" ? (
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    linkMutation.mutate();
                  }}
                  className="space-y-3"
                >
                  <FormField
                    label="Link"
                    required
                    hint="HTTPS only; blocked, private, or paywalled links can't be read"
                    error={submitApiError?.fieldError("url")}
                  >
                    {(control) => (
                      <Input
                        {...control}
                        type="url"
                        value={url}
                        maxLength={2048}
                        onChange={(event) => setUrl(event.target.value)}
                        placeholder="https://…"
                      />
                    )}
                  </FormField>
                  <FormField label="Note (optional)">
                    {(control) => (
                      <Textarea
                        {...control}
                        value={context}
                        maxLength={2000}
                        onChange={(event) => setContext(event.target.value)}
                        placeholder="What is this and what do you want from it?"
                        className="min-h-20"
                      />
                    )}
                  </FormField>
                  {submitError && !submitApiError ? (
                    <Alert variant="error" title="Couldn't submit">
                      {submitError.message}
                    </Alert>
                  ) : null}
                  <Button
                    type="submit"
                    className="w-full"
                    isLoading={linkMutation.isPending}
                    loadingLabel="Submitting"
                    disabled={url.trim() === ""}
                  >
                    Analyze link
                  </Button>
                </form>
              ) : (
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    fileMutation.mutate();
                  }}
                  className="space-y-3"
                >
                  <FormField
                    label="Ready file"
                    required
                    hint="Upload files in Resources first; PDF, text, and Markdown can be read"
                    error={submitApiError?.fieldError("resource_id")}
                  >
                    {(control) => (
                      <Select
                        {...control}
                        value={resourceId}
                        onChange={(event) => setResourceId(event.target.value)}
                      >
                        <option value="">Choose a file…</option>
                        {(readyFilesQuery.data?.data ?? []).map((resource) => (
                          <option key={resource.id} value={resource.id}>
                            {resource.title}
                          </option>
                        ))}
                      </Select>
                    )}
                  </FormField>
                  <FormField label="Note (optional)">
                    {(control) => (
                      <Textarea
                        {...control}
                        value={context}
                        maxLength={2000}
                        onChange={(event) => setContext(event.target.value)}
                        className="min-h-20"
                      />
                    )}
                  </FormField>
                  {submitError && !submitApiError ? (
                    <Alert variant="error" title="Couldn't submit">
                      {submitError.message}
                    </Alert>
                  ) : null}
                  <Button
                    type="submit"
                    className="w-full"
                    isLoading={fileMutation.isPending}
                    loadingLabel="Submitting"
                    disabled={resourceId === ""}
                  >
                    Analyze file
                  </Button>
                </form>
              )}
            </CardContent>
          </Card>

          {/* Item list */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Inbox
                  aria-hidden="true"
                  className="size-5 text-brand-primary"
                />
                Recent intake
              </CardTitle>
            </CardHeader>
            <CardContent>
              {listQuery.isPending ? (
                <div className="space-y-2">
                  <Skeleton className="h-12 rounded-md" />
                  <Skeleton className="h-12 rounded-md" />
                </div>
              ) : listQuery.isError ? (
                <ErrorState
                  title="Couldn't load intake"
                  onRetry={() => void listQuery.refetch()}
                />
              ) : items.length === 0 ? (
                <p className="text-body text-text-muted">
                  Nothing yet. Submit a link or file above to get started.
                </p>
              ) : (
                <ul className="space-y-1.5">
                  {items.map((item) => (
                    <li key={item.id}>
                      <button
                        type="button"
                        aria-pressed={selectedId === item.id}
                        onClick={() => setSelectedId(item.id)}
                        className={cn(
                          "flex w-full items-center gap-2 rounded-md border px-3 py-2 text-left transition-colors",
                          selectedId === item.id
                            ? "border-brand-primary/40 bg-bg-interactive"
                            : "border-border-subtle hover:border-border-strong",
                        )}
                      >
                        <span
                          aria-hidden="true"
                          className={cn(
                            "size-2 shrink-0 rounded-full",
                            STATE_DOT[item.state] ?? "bg-status-info",
                          )}
                        />
                        <span className="min-w-0 flex-1 truncate text-body text-text-primary">
                          {item.source.type === "link"
                            ? (item.source.url ?? "Linked source")
                            : (item.source.resource?.title ?? "File source")}
                        </span>
                        {item.state === "awaiting_review" ? (
                          <Badge variant="brand">Review</Badge>
                        ) : null}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Selected detail */}
        <div>
          {selectedId === null ? (
            <Card>
              <CardContent className="py-12">
                <EmptyState
                  icon={Sparkles}
                  title="Select an intake item"
                  description="Pick something from the list, or submit a new link or file to watch it move through extraction and review."
                />
              </CardContent>
            </Card>
          ) : selectedQuery.isPending ? (
            <Skeleton className="h-72 rounded-lg" />
          ) : selectedQuery.isError ? (
            <ErrorState
              title="Couldn't load this item"
              onRetry={() => void selectedQuery.refetch()}
            />
          ) : selected ? (
            <IntakeDetail
              item={selected}
              busy={actionMutation.isPending}
              onCancel={() =>
                actionMutation.mutate({ action: "cancel", itemId: selected.id })
              }
              onRetry={() =>
                actionMutation.mutate({ action: "retry", itemId: selected.id })
              }
            >
              {selected.state === "awaiting_review" ? (
                suggestionsQuery.isPending ? (
                  <Skeleton className="h-40 rounded-md" />
                ) : suggestionsQuery.data &&
                  suggestionsQuery.data.length > 0 ? (
                  <IntakeReview
                    suggestions={suggestionsQuery.data}
                    courses={coursesQuery.data?.data ?? []}
                    busy={confirmMutation.isPending}
                    error={
                      confirmMutation.error instanceof ApiError &&
                      confirmMutation.error.status === 409
                        ? "This item changed — reload and review again."
                        : (confirmMutation.error?.message ?? null)
                    }
                    onConfirm={(decisions) =>
                      confirmMutation.mutate({ itemId: selected.id, decisions })
                    }
                  />
                ) : (
                  <p className="text-body text-text-muted">
                    No suggestions were produced for this source.
                  </p>
                )
              ) : null}
            </IntakeDetail>
          ) : null}
        </div>
      </div>
    </div>
  );
}
