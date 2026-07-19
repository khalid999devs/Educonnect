"use client";

import {
  Alert,
  Badge,
  Card,
  CardContent,
  cn,
  EmptyState,
  ErrorState,
  Select,
  Skeleton,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Compass,
  LayoutTemplate,
  ListChecks,
  Sparkles,
  Target,
  Wrench,
} from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useState } from "react";

import {
  dismissPrompt,
  dismissTool,
  dismissWorkflow,
  getGuidance,
  listGuidanceCategories,
  recordPromptCopy,
  savePrompt,
  saveTool,
  saveWorkflow,
  undismissPrompt,
  undismissTool,
  undismissWorkflow,
  unsavePrompt,
  unsaveTool,
  unsaveWorkflow,
  type GuidancePreference,
} from "@/lib/api/guidance";
import { guidanceKeys } from "@/lib/query-keys";
import { PromptCard, ToolCard, WorkflowCard } from "./guidance-cards";

type Kind = "tool" | "prompt" | "workflow";

export function ToolsPromptsView() {
  const queryClient = useQueryClient();
  const [category, setCategory] = useState<string | null>(null);
  const [preference, setPreference] = useState<GuidancePreference>("all");
  const [busyId, setBusyId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const categoriesQuery = useQuery({
    queryKey: guidanceKeys.categories(),
    queryFn: listGuidanceCategories,
    staleTime: 5 * 60_000,
  });

  const bundleQuery = useQuery({
    queryKey: guidanceKeys.bundle(category ?? ""),
    queryFn: () => getGuidance(category as string),
    enabled: category !== null,
  });

  const invalidateBundle = () => {
    void queryClient.invalidateQueries({ queryKey: guidanceKeys.all });
  };

  const preferenceMutation = useMutation({
    mutationFn: async ({
      kind,
      id,
      action,
    }: {
      kind: Kind;
      id: string;
      action: "save" | "unsave" | "dismiss" | "undismiss";
    }) => {
      const table = {
        tool: {
          save: saveTool,
          unsave: unsaveTool,
          dismiss: dismissTool,
          undismiss: undismissTool,
        },
        prompt: {
          save: savePrompt,
          unsave: unsavePrompt,
          dismiss: dismissPrompt,
          undismiss: undismissPrompt,
        },
        workflow: {
          save: saveWorkflow,
          unsave: unsaveWorkflow,
          dismiss: dismissWorkflow,
          undismiss: undismissWorkflow,
        },
      } as const;

      await table[kind][action](id);
    },
    onMutate: ({ id }) => setBusyId(id),
    onSettled: () => setBusyId(null),
    onSuccess: invalidateBundle,
    onError: () => setNotice("That change didn't save. Please try again."),
  });

  const copyMutation = useMutation({
    mutationFn: (promptId: string) => recordPromptCopy(promptId),
    onSuccess: invalidateBundle,
  });

  const categories = categoriesQuery.data ?? [];
  const bundle = bundleQuery.data;

  const filterByPreference = <
    T extends { viewer_state: { saved: boolean; dismissed: boolean } },
  >(
    items: T[],
  ): T[] => {
    if (preference === "saved") {
      return items.filter((item) => item.viewer_state.saved);
    }
    if (preference === "dismissed") {
      return items.filter((item) => item.viewer_state.dismissed);
    }
    if (preference === "none") {
      return items.filter(
        (item) => !item.viewer_state.saved && !item.viewer_state.dismissed,
      );
    }

    return items;
  };

  const tools = bundle ? filterByPreference(bundle.tools) : [];
  const prompts = bundle ? filterByPreference(bundle.prompts) : [];
  const workflows = bundle ? filterByPreference(bundle.workflows) : [];
  const bundleEmpty =
    bundle !== undefined &&
    tools.length === 0 &&
    prompts.length === 0 &&
    workflows.length === 0;

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
          <h1 className="text-h2 text-text-primary">Tools &amp; prompts</h1>
          <p className="text-body-lg text-text-secondary">
            Start from a goal. Every recommendation explains why it fits, what
            it costs, how it handles your data, and how to use it with academic
            integrity.
          </p>
        </div>
      </header>

      {notice ? (
        <Alert variant="info" title="Heads up">
          {notice}
        </Alert>
      ) : null}

      {/* Goal chooser */}
      <Card>
        <CardContent className="space-y-3 py-5">
          <div className="flex items-center gap-2">
            <Target aria-hidden="true" className="size-5 text-brand-primary" />
            <h2 className="text-h4 text-text-primary">Choose a goal</h2>
          </div>

          {categoriesQuery.isPending ? (
            <div className="flex flex-wrap gap-2">
              <Skeleton className="h-9 w-32 rounded-full" />
              <Skeleton className="h-9 w-40 rounded-full" />
              <Skeleton className="h-9 w-28 rounded-full" />
            </div>
          ) : categoriesQuery.isError ? (
            <ErrorState
              title="Goals could not load"
              onRetry={() => void categoriesQuery.refetch()}
            />
          ) : categories.length === 0 ? (
            <EmptyState
              icon={Compass}
              title="Guidance is being curated"
              description="Reviewed tools, prompts, and workflows will appear here as our team publishes them. Nothing is auto-generated."
            />
          ) : (
            <div className="flex flex-wrap gap-2">
              {categories.map((goal) => (
                <button
                  key={goal.key}
                  type="button"
                  aria-pressed={category === goal.key}
                  onClick={() => setCategory(goal.key)}
                  className={cn(
                    "rounded-full border px-3.5 py-1.5 text-body transition-colors focus-visible:outline-2 focus-visible:outline-brand-focus",
                    category === goal.key
                      ? "border-brand-primary/40 bg-bg-interactive text-brand-primary"
                      : "border-border-default text-text-secondary hover:border-border-strong",
                  )}
                >
                  {goal.name}
                </button>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {category === null ? (
        <Card>
          <CardContent className="py-10">
            <EmptyState
              icon={Sparkles}
              title="Pick a goal to see guidance"
              description="Choose what you're trying to do above and we'll show the tools, prompts, and workflows curated for it."
            />
          </CardContent>
        </Card>
      ) : bundleQuery.isPending ? (
        <div className="space-y-3">
          <Skeleton className="h-48 rounded-lg" />
          <Skeleton className="h-48 rounded-lg" />
        </div>
      ) : bundleQuery.isError ? (
        <ErrorState
          title="This goal's guidance could not load"
          onRetry={() => void bundleQuery.refetch()}
        />
      ) : bundle ? (
        <div className="space-y-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-h3 text-text-primary">
                {bundle.category.name}
              </h2>
              {bundle.category.description ? (
                <p className="text-body text-text-secondary">
                  {bundle.category.description}
                </p>
              ) : null}
            </div>
            <label className="flex items-center gap-2 text-body text-text-muted">
              Show
              <Select
                value={preference}
                onChange={(event) =>
                  setPreference(event.target.value as GuidancePreference)
                }
                className="w-40"
                aria-label="Filter by your preference"
              >
                <option value="all">Everything</option>
                <option value="saved">Saved</option>
                <option value="none">Not yet decided</option>
                <option value="dismissed">Dismissed</option>
              </Select>
            </label>
          </div>

          {bundleEmpty ? (
            <Card>
              <CardContent className="py-10">
                <EmptyState
                  icon={ListChecks}
                  title="Nothing matches this filter"
                  description="Try a different preference filter, or pick another goal."
                />
              </CardContent>
            </Card>
          ) : null}

          {tools.length > 0 ? (
            <section className="space-y-3">
              <h3 className="flex items-center gap-2 text-h4 text-text-primary">
                <Wrench aria-hidden="true" className="size-5 text-status-ai" />
                Tools
              </h3>
              <div className="grid gap-4 xl:grid-cols-2">
                {tools.map((tool) => (
                  <ToolCard
                    key={tool.id}
                    tool={tool}
                    busy={busyId === tool.id}
                    onToggleSave={() =>
                      preferenceMutation.mutate({
                        kind: "tool",
                        id: tool.id,
                        action: tool.viewer_state.saved ? "unsave" : "save",
                      })
                    }
                    onToggleDismiss={() =>
                      preferenceMutation.mutate({
                        kind: "tool",
                        id: tool.id,
                        action: tool.viewer_state.dismissed
                          ? "undismiss"
                          : "dismiss",
                      })
                    }
                  />
                ))}
              </div>
            </section>
          ) : null}

          {prompts.length > 0 ? (
            <section className="space-y-3">
              <h3 className="flex items-center gap-2 text-h4 text-text-primary">
                <Sparkles
                  aria-hidden="true"
                  className="size-5 text-brand-primary"
                />
                Prompts
              </h3>
              <div className="grid gap-4 xl:grid-cols-2">
                {prompts.map((prompt) => (
                  <PromptCard
                    key={prompt.id}
                    prompt={prompt}
                    busy={busyId === prompt.id}
                    onToggleSave={() =>
                      preferenceMutation.mutate({
                        kind: "prompt",
                        id: prompt.id,
                        action: prompt.viewer_state.saved ? "unsave" : "save",
                      })
                    }
                    onToggleDismiss={() =>
                      preferenceMutation.mutate({
                        kind: "prompt",
                        id: prompt.id,
                        action: prompt.viewer_state.dismissed
                          ? "undismiss"
                          : "dismiss",
                      })
                    }
                    onCopy={() => copyMutation.mutate(prompt.id)}
                  />
                ))}
              </div>
            </section>
          ) : null}

          {workflows.length > 0 ? (
            <section className="space-y-3">
              <h3 className="flex items-center gap-2 text-h4 text-text-primary">
                <ListChecks
                  aria-hidden="true"
                  className="size-5 text-status-research"
                />
                Workflows
              </h3>
              <div className="grid gap-4">
                {workflows.map((workflow) => (
                  <WorkflowCard
                    key={workflow.id}
                    workflow={workflow}
                    busy={busyId === workflow.id}
                    onToggleSave={() =>
                      preferenceMutation.mutate({
                        kind: "workflow",
                        id: workflow.id,
                        action: workflow.viewer_state.saved ? "unsave" : "save",
                      })
                    }
                    onToggleDismiss={() =>
                      preferenceMutation.mutate({
                        kind: "workflow",
                        id: workflow.id,
                        action: workflow.viewer_state.dismissed
                          ? "undismiss"
                          : "dismiss",
                      })
                    }
                  />
                ))}
              </div>
            </section>
          ) : null}

          {bundle.templates.length > 0 ? (
            <section className="space-y-3">
              <h3 className="flex items-center gap-2 text-h4 text-text-primary">
                <LayoutTemplate
                  aria-hidden="true"
                  className="size-5 text-status-info"
                />
                Related templates
              </h3>
              <div className="flex flex-wrap gap-2">
                {bundle.templates.map((template) => (
                  <Link
                    key={template.id}
                    href="/templates"
                    className="flex items-center gap-2 rounded-md border border-border-default px-3 py-2 text-body text-text-secondary hover:border-border-strong"
                  >
                    <LayoutTemplate
                      aria-hidden="true"
                      className="size-4 text-status-info"
                    />
                    {template.title}
                    <Badge variant="success">Free</Badge>
                  </Link>
                ))}
              </div>
              <p className="text-caption text-text-muted">
                Open the{" "}
                <Link
                  href="/templates"
                  className="text-brand-primary hover:underline"
                >
                  Templates
                </Link>{" "}
                library to preview and copy any of these.
              </p>
            </section>
          ) : null}
        </div>
      ) : null}

      <p className="flex items-center gap-1.5 text-caption text-text-muted">
        <Sparkles aria-hidden="true" className="size-3.5" />
        Every tool, prompt, and workflow here is curated and reviewed by our
        team, never auto-generated or ranked by ads.
      </p>
    </div>
  );
}
