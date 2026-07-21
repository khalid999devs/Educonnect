"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import {
  keepPreviousData,
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { ListChecks, ShieldCheck, Sparkles, Wrench } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { PromptCard, WorkflowCard } from "@/components/guidance/guidance-cards";
import { useCatalogPreference } from "@/components/shared/catalog-preference";
import { IconChip } from "@/components/shared/icon-chip";
import { PageCover } from "@/components/shared/page-cover";
import { SavedFilterChip } from "@/components/shared/saved-filter-chip";
import {
  dismissPrompt,
  dismissWorkflow,
  listPrompts,
  listWorkflows,
  recordPromptCopy,
  savePrompt,
  saveWorkflow,
  undismissPrompt,
  undismissWorkflow,
  unsavePrompt,
  unsaveWorkflow,
} from "@/lib/api/guidance";
import {
  dismissTool,
  listToolCategories,
  listTools,
  saveTool,
  searchToolsByScenario,
  undismissTool,
  unsaveTool,
  type ScenarioSearch,
  type ToolSort,
} from "@/lib/api/tools";
import {
  promptKeys,
  scenarioKeys,
  toolCategoryKeys,
  toolKeys,
  workflowKeys,
} from "@/lib/query-keys";
import { GoalChips } from "./goal-chips";
import { SmartSearch } from "./smart-search";
import { ToolCard } from "./tool-card";

/**
 * AI Tools - the curated catalog with a scenario-aware smart search.
 *
 * Two modes, one bar:
 *   - empty search  -> recommended browse via `GET /tools`, cursor-paginated,
 *     narrowed by the goal chips and the SERVER-side `preference` filter.
 *   - >= 3 chars    -> `POST /tools/scenario-search` ranks the same curated
 *     candidate set and explains each hit.
 *
 * The ranker is retrieval-from-PostgreSQL plus an AI re-rank, cached 15 minutes
 * server-side, and it always degrades to an unranked candidate set. So a
 * degraded response is rendered as normal results with an honest caption, never
 * as an error state. Only a transport failure is an error.
 *
 * Nothing here is a dialog. Filters, goals, ranking and curation notes are all
 * inline on the page.
 */
const SCENARIO_MIN_LENGTH = 3;
const SCENARIO_MAX_LENGTH = 600;
const DEBOUNCE_MS = 400;
const PER_PAGE = 24;

type Kind = "tool" | "prompt" | "workflow";
type PreferenceAction = "save" | "unsave" | "dismiss" | "undismiss";

export function AiToolsView() {
  const queryClient = useQueryClient();

  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [category, setCategory] = useState<string | null>(null);
  /* Server-side and URL-synced: `?preference=saved` is a linkable saved view,
     and the filter is never applied to a loaded page client-side. */
  const { preference, setPreference, savedOnly, toggleSaved } =
    useCatalogPreference();
  const [sort, setSort] = useState<ToolSort>("name");
  const [busyId, setBusyId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  /* Debounced, never blocking: `search` stays the controlled input value so
     typing is always instant; only the ranker input is delayed. */
  useEffect(() => {
    const timer = window.setTimeout(
      () => setDebouncedSearch(search.trim().slice(0, SCENARIO_MAX_LENGTH)),
      DEBOUNCE_MS,
    );

    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    if (notice === null) {
      return;
    }

    const timer = window.setTimeout(() => setNotice(null), 8_000);

    return () => window.clearTimeout(timer);
  }, [notice]);

  const scenarioActive = debouncedSearch.length >= SCENARIO_MIN_LENGTH;

  const categoriesQuery = useQuery({
    queryKey: toolCategoryKeys.list(),
    queryFn: listToolCategories,
    staleTime: 5 * 60_000,
  });

  const toolListParams = useMemo(
    () => ({
      category: category ?? undefined,
      preference,
      sort,
    }),
    [category, preference, sort],
  );

  const toolsQuery = useInfiniteQuery({
    queryKey: toolKeys.list(toolListParams),
    queryFn: ({ pageParam }) =>
      listTools({ ...toolListParams, perPage: PER_PAGE, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
    enabled: !scenarioActive,
  });

  const scenarioQuery = useQuery({
    queryKey: scenarioKeys.search({
      scenario: debouncedSearch,
      category: category ?? undefined,
    }),
    queryFn: () =>
      searchToolsByScenario({
        scenario: debouncedSearch,
        category: category ?? undefined,
      }),
    enabled: scenarioActive,
    /* Matches the server-side cache window, and keeps the previous ranking on
       screen while a newer one is in flight. */
    staleTime: 15 * 60_000,
    placeholderData: keepPreviousData,
  });

  const guidanceParams = useMemo(
    () => ({ category: category ?? undefined, preference }),
    [category, preference],
  );

  const promptsQuery = useInfiniteQuery({
    queryKey: promptKeys.list(guidanceParams),
    queryFn: ({ pageParam }) =>
      listPrompts({ ...guidanceParams, perPage: PER_PAGE, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const workflowsQuery = useInfiniteQuery({
    queryKey: workflowKeys.list(guidanceParams),
    queryFn: ({ pageParam }) =>
      listWorkflows({
        ...guidanceParams,
        perPage: PER_PAGE,
        cursor: pageParam,
      }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  /* Every list and every ranking embeds viewer_state, so a preference change
     invalidates every whole family. */
  const invalidateCatalog = () => {
    void queryClient.invalidateQueries({ queryKey: toolKeys.all });
    void queryClient.invalidateQueries({ queryKey: promptKeys.all });
    void queryClient.invalidateQueries({ queryKey: workflowKeys.all });
    void queryClient.invalidateQueries({ queryKey: scenarioKeys.all });
  };

  const preferenceMutation = useMutation({
    mutationFn: async ({
      kind,
      id,
      action,
    }: {
      kind: Kind;
      id: string;
      action: PreferenceAction;
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
    onSuccess: invalidateCatalog,
    onError: () => setNotice("That change didn't save. Please try again."),
  });

  const copyMutation = useMutation({
    mutationFn: (promptId: string) => recordPromptCopy(promptId),
    onSuccess: invalidateCatalog,
  });

  const togglePreference = (
    kind: Kind,
    id: string,
    state: { saved: boolean; dismissed: boolean },
    field: "saved" | "dismissed",
  ) => {
    const action: PreferenceAction =
      field === "saved"
        ? state.saved
          ? "unsave"
          : "save"
        : state.dismissed
          ? "undismiss"
          : "dismiss";

    preferenceMutation.mutate({ kind, id, action });
  };

  const categories = categoriesQuery.data ?? [];
  const browseTools = useMemo(
    () => (toolsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [toolsQuery.data],
  );
  const prompts = useMemo(
    () => (promptsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [promptsQuery.data],
  );
  const workflows = useMemo(
    () => (workflowsQuery.data?.pages ?? []).flatMap((page) => page.data),
    [workflowsQuery.data],
  );

  const ranking = scenarioQuery.data;
  const narrowed = category !== null || preference !== "all";

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/study-desk.jpg"
        headingLevel={1}
        tall
        priority
        title="The right tool, with the reasoning"
        subtitle="Every tool here is reviewed by a human and comes with why it fits, what it costs, how it treats your data, and where the recommendation came from."
        search={
          <SmartSearch
            value={search}
            onChange={setSearch}
            pending={scenarioActive && scenarioQuery.isFetching}
            active={scenarioActive}
            preference={preference}
            onPreferenceChange={setPreference}
            sort={sort}
            onSortChange={setSort}
          />
        }
      />

      {notice ? (
        <Alert variant="info" title="Heads up">
          {notice}
        </Alert>
      ) : null}

      <GoalChips
        categories={categories}
        value={category}
        onChange={setCategory}
        isPending={categoriesQuery.isPending}
        isError={categoriesQuery.isError}
        onRetry={() => void categoriesQuery.refetch()}
      />

      <div className="flex flex-wrap items-center gap-x-3 gap-y-2 motion-safe:animate-fade-up motion-safe:[animation-delay:120ms]">
        <SavedFilterChip
          accent="aiTools"
          active={savedOnly}
          onToggle={toggleSaved}
          describes="tools, prompts and workflows"
        />
        <p className="text-caption text-text-muted">
          {savedOnly
            ? scenarioActive
              ? "Saved narrows browsing, prompts and workflows. Ranking always searches the whole curated catalog."
              : "Showing only what you saved, across tools, prompts and workflows."
            : "Save anything with the bookmark on its card, then come back here."}
        </p>
      </div>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
        <Card className="motion-safe:animate-fade-up motion-safe:[animation-delay:160ms]">
          <CardHeader className="flex flex-wrap items-center justify-between gap-2">
            <CardTitle className="flex items-center gap-2.5">
              <IconChip icon={Wrench} accent="aiTools" size="sm" />
              {scenarioActive ? "Ranked for your situation" : "Recommended"}
            </CardTitle>
            {scenarioActive && ranking ? (
              <Badge variant={ranking.ai_ranked ? "ai" : "neutral"}>
                {ranking.ai_ranked ? "AI ranked" : "Keyword ranked"}
              </Badge>
            ) : null}
          </CardHeader>
          <CardContent>
            {scenarioActive ? (
              <ScenarioResults
                busyId={busyId}
                onToggle={togglePreference}
                ranking={ranking}
                isPending={scenarioQuery.isPending}
                isError={scenarioQuery.isError}
                onRetry={() => void scenarioQuery.refetch()}
              />
            ) : toolsQuery.isPending ? (
              <div className="grid gap-4 sm:grid-cols-2">
                <Skeleton className="h-72 rounded-lg" />
                <Skeleton className="h-72 rounded-lg" />
              </div>
            ) : toolsQuery.isError ? (
              <ErrorState
                title="Tools could not load"
                onRetry={() => void toolsQuery.refetch()}
              />
            ) : browseTools.length === 0 ? (
              <EmptyState
                icon={Wrench}
                title={
                  savedOnly
                    ? "Nothing saved yet"
                    : narrowed
                      ? "No tools match"
                      : "Tools are being curated"
                }
                description={
                  savedOnly
                    ? "Turn off the Saved filter to browse the catalog, then use the bookmark on any tool card to keep it here."
                    : narrowed
                      ? "Try another goal, clear the preference filter, or describe your situation in the search bar."
                      : "Reviewed tools appear here as our team publishes them. Nothing is auto-generated or ranked by ads."
                }
                action={
                  savedOnly ? (
                    <Button variant="secondary" onClick={toggleSaved}>
                      Browse everything
                    </Button>
                  ) : null
                }
              />
            ) : (
              <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  {browseTools.map((tool) => (
                    <ToolCard
                      key={tool.id}
                      tool={tool}
                      busy={busyId === tool.id}
                      onToggleSave={() =>
                        togglePreference(
                          "tool",
                          tool.id,
                          tool.viewer_state,
                          "saved",
                        )
                      }
                      onToggleDismiss={() =>
                        togglePreference(
                          "tool",
                          tool.id,
                          tool.viewer_state,
                          "dismissed",
                        )
                      }
                    />
                  ))}
                </div>
                {toolsQuery.hasNextPage ? (
                  <div className="flex justify-center">
                    <Button
                      variant="secondary"
                      isLoading={toolsQuery.isFetchingNextPage}
                      loadingLabel="Loading more"
                      onClick={() => void toolsQuery.fetchNextPage()}
                    >
                      Load more tools
                    </Button>
                  </div>
                ) : null}
              </div>
            )}
          </CardContent>
        </Card>

        <CurationRail
          aiRanked={
            scenarioActive && ranking !== undefined && ranking.ai_ranked
          }
          cached={scenarioActive && ranking?.cached === true}
          model={scenarioActive ? ranking?.model : undefined}
          provider={scenarioActive ? ranking?.provider : undefined}
          disclaimer={scenarioActive ? ranking?.disclaimer : undefined}
        />
      </div>

      <section className="scroll-reveal space-y-3">
        <h2 className="flex items-center gap-2.5 text-h3 text-text-primary">
          <IconChip icon={Sparkles} accent="aiTools" size="md" />
          Prompts
        </h2>
        {promptsQuery.isPending ? (
          <div className="grid gap-4 xl:grid-cols-2">
            <Skeleton className="h-64 rounded-lg" />
            <Skeleton className="h-64 rounded-lg" />
          </div>
        ) : promptsQuery.isError ? (
          <ErrorState
            title="Prompts could not load"
            onRetry={() => void promptsQuery.refetch()}
          />
        ) : prompts.length === 0 ? (
          <EmptyState
            icon={Sparkles}
            title={
              savedOnly
                ? "No saved prompts yet"
                : narrowed
                  ? "No prompts match"
                  : "Prompts are being curated"
            }
            description={
              savedOnly
                ? "Turn off the Saved filter, then use the bookmark on any prompt to keep it here."
                : narrowed
                  ? "Try another goal or clear the preference filter."
                  : "Reviewed prompt templates, each with an academic-integrity note, appear here as our team publishes them."
            }
            action={
              savedOnly ? (
                <Button variant="secondary" onClick={toggleSaved}>
                  Browse everything
                </Button>
              ) : null
            }
          />
        ) : (
          <div className="space-y-4">
            <div className="grid gap-4 xl:grid-cols-2">
              {prompts.map((prompt) => (
                <PromptCard
                  key={prompt.id}
                  prompt={prompt}
                  busy={busyId === prompt.id}
                  onToggleSave={() =>
                    togglePreference(
                      "prompt",
                      prompt.id,
                      prompt.viewer_state,
                      "saved",
                    )
                  }
                  onToggleDismiss={() =>
                    togglePreference(
                      "prompt",
                      prompt.id,
                      prompt.viewer_state,
                      "dismissed",
                    )
                  }
                  onCopy={() => copyMutation.mutate(prompt.id)}
                />
              ))}
            </div>
            {promptsQuery.hasNextPage ? (
              <div className="flex justify-center">
                <Button
                  variant="secondary"
                  isLoading={promptsQuery.isFetchingNextPage}
                  loadingLabel="Loading more"
                  onClick={() => void promptsQuery.fetchNextPage()}
                >
                  Load more prompts
                </Button>
              </div>
            ) : null}
          </div>
        )}
      </section>

      <section className="scroll-reveal space-y-3">
        <h2 className="flex items-center gap-2.5 text-h3 text-text-primary">
          <IconChip icon={ListChecks} accent="secondBrain" size="md" />
          Workflows
        </h2>
        {workflowsQuery.isPending ? (
          <div className="space-y-4">
            <Skeleton className="h-64 rounded-lg" />
          </div>
        ) : workflowsQuery.isError ? (
          <ErrorState
            title="Workflows could not load"
            onRetry={() => void workflowsQuery.refetch()}
          />
        ) : workflows.length === 0 ? (
          <EmptyState
            icon={ListChecks}
            title={
              savedOnly
                ? "No saved workflows yet"
                : narrowed
                  ? "No workflows match"
                  : "Workflows are being curated"
            }
            description={
              savedOnly
                ? "Turn off the Saved filter, then use the bookmark on any workflow to keep it here."
                : narrowed
                  ? "Try another goal or clear the preference filter."
                  : "Step-by-step recipes that chain tools, prompts and templates appear here as our team publishes them."
            }
            action={
              savedOnly ? (
                <Button variant="secondary" onClick={toggleSaved}>
                  Browse everything
                </Button>
              ) : null
            }
          />
        ) : (
          <div className="space-y-4">
            {workflows.map((workflow) => (
              <WorkflowCard
                key={workflow.id}
                workflow={workflow}
                busy={busyId === workflow.id}
                onToggleSave={() =>
                  togglePreference(
                    "workflow",
                    workflow.id,
                    workflow.viewer_state,
                    "saved",
                  )
                }
                onToggleDismiss={() =>
                  togglePreference(
                    "workflow",
                    workflow.id,
                    workflow.viewer_state,
                    "dismissed",
                  )
                }
              />
            ))}
            {workflowsQuery.hasNextPage ? (
              <div className="flex justify-center">
                <Button
                  variant="secondary"
                  isLoading={workflowsQuery.isFetchingNextPage}
                  loadingLabel="Loading more"
                  onClick={() => void workflowsQuery.fetchNextPage()}
                >
                  Load more workflows
                </Button>
              </div>
            ) : null}
          </div>
        )}
      </section>
    </div>
  );
}

/**
 * Scenario results. A degraded ranking is a normal render with an honest
 * caption, because the endpoint always returns the candidate set; only a
 * transport failure reaches the error branch.
 */
function ScenarioResults({
  ranking,
  isPending,
  isError,
  onRetry,
  busyId,
  onToggle,
}: {
  ranking: ScenarioSearch | undefined;
  isPending: boolean;
  isError: boolean;
  onRetry: () => void;
  busyId: string | null;
  onToggle: (
    kind: Kind,
    id: string,
    state: { saved: boolean; dismissed: boolean },
    field: "saved" | "dismissed",
  ) => void;
}) {
  if (isPending) {
    return (
      <div className="grid gap-4 sm:grid-cols-2">
        <Skeleton className="h-72 rounded-lg" />
        <Skeleton className="h-72 rounded-lg" />
      </div>
    );
  }

  if (isError) {
    return <ErrorState title="That search could not run" onRetry={onRetry} />;
  }

  const results = ranking?.results ?? [];

  if (results.length === 0) {
    return (
      <EmptyState
        icon={Wrench}
        title="Nothing in the catalog fits that yet"
        description="Try describing the situation differently, or clear the search to browse everything we recommend."
      />
    );
  }

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {results.map((result, index) => (
        <ToolCard
          key={result.id}
          tool={result}
          matchReason={result.match_reason}
          aiRanked={ranking?.ai_ranked ?? false}
          rank={index + 1}
          busy={busyId === result.id}
          onToggleSave={() =>
            onToggle("tool", result.id, result.viewer_state, "saved")
          }
          onToggleDismiss={() =>
            onToggle("tool", result.id, result.viewer_state, "dismissed")
          }
        />
      ))}
    </div>
  );
}

/** Curation transparency: a product invariant, so it is always on screen. */
function CurationRail({
  aiRanked,
  cached,
  model,
  provider,
  disclaimer,
}: {
  aiRanked: boolean;
  cached: boolean;
  model?: string;
  provider?: string;
  disclaimer?: string;
}) {
  return (
    <Card className="motion-safe:animate-fade-up motion-safe:[animation-delay:240ms]">
      <CardHeader>
        <CardTitle className="flex items-center gap-2.5">
          <IconChip icon={ShieldCheck} accent="community" size="sm" />
          How this list is built
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <ul className="space-y-2.5">
          <li className="flex gap-2.5 text-body text-text-secondary">
            <span
              aria-hidden="true"
              className="mt-2 size-1.5 shrink-0 rounded-full bg-status-ai"
            />
            Every entry is reviewed by a person and carries its provenance, cost
            note, privacy note, and limitations.
          </li>
          <li className="flex gap-2.5 text-body text-text-secondary">
            <span
              aria-hidden="true"
              className="mt-2 size-1.5 shrink-0 rounded-full bg-status-success"
            />
            Nothing is sponsored and nothing is ranked by ads.
          </li>
          <li className="flex gap-2.5 text-body text-text-secondary">
            <span
              aria-hidden="true"
              className="mt-2 size-1.5 shrink-0 rounded-full bg-status-info"
            />
            Search only reorders and explains this same curated set. It can
            never introduce a tool that is not in it.
          </li>
        </ul>

        {provider !== undefined ? (
          <div className="space-y-1.5 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-3">
            <p className="text-caption font-medium text-text-primary">
              This ranking
            </p>
            <p className="text-caption text-text-muted">
              {aiRanked
                ? `Ranked by ${provider}${model ? ` (${model})` : ""}.`
                : "Ranked by keyword match. The AI ranker was unavailable, so results are the plain candidate set."}
              {cached ? " Served from cache." : ""}
            </p>
            {disclaimer ? (
              <p className="text-caption text-text-muted">{disclaimer}</p>
            ) : null}
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
