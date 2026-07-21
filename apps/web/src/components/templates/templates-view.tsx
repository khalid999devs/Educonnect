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
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import {
  Archive,
  ArchiveRestore,
  LayoutTemplate,
  Library,
  Pencil,
  Search,
} from "lucide-react";
import { useCatalogPreference } from "@/components/shared/catalog-preference";
import { PageCover } from "@/components/shared/page-cover";
import { SavedFilterChip } from "@/components/shared/saved-filter-chip";
import { useEffect, useMemo, useState } from "react";

import {
  ACTIVE_COURSE_LIST_PARAMS,
  fetchAllActiveCourses,
} from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import {
  archiveTemplateCopy,
  copyTemplate,
  dismissTemplate,
  listTemplateCopies,
  listTemplates,
  restoreTemplateCopy,
  saveTemplate,
  undismissTemplate,
  unsaveTemplate,
  updateTemplateCopy,
  type CopyDestination,
  type Template,
  type TemplateCopy,
} from "@/lib/api/templates";
import { courseKeys, templateKeys } from "@/lib/query-keys";
import { TemplateCard } from "./template-card";
import {
  CopyEditorDialog,
  TemplatePreviewDialog,
  UseTemplateDialog,
} from "./template-dialogs";

export function TemplatesView() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [category, setCategory] = useState<string | null>(null);
  /* Server-side and URL-synced: `/templates?preference=saved` is a linkable
     saved view. The filter is a query parameter, never a client-side pass over
     one loaded page, which would drop saved rows sitting past the cursor. */
  const { preference, savedOnly, toggleSaved } = useCatalogPreference();
  const [includeArchived, setIncludeArchived] = useState(false);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const [previewing, setPreviewing] = useState<Template | null>(null);
  const [using, setUsing] = useState<Template | null>(null);
  const [editing, setEditing] = useState<TemplateCopy | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(
      () => setDebouncedSearch(search.trim()),
      300,
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

  const listParams = useMemo(
    () => ({
      search: debouncedSearch === "" ? undefined : debouncedSearch,
      category: category ?? undefined,
      preference,
    }),
    [debouncedSearch, category, preference],
  );

  const templatesQuery = useInfiniteQuery({
    queryKey: templateKeys.list(listParams),
    queryFn: ({ pageParam }) =>
      listTemplates({ ...listParams, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const copiesQuery = useInfiniteQuery({
    queryKey: templateKeys.copies({ includeArchived }),
    queryFn: ({ pageParam }) =>
      listTemplateCopies({ includeArchived, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.meta.pagination.next_cursor ?? undefined,
  });

  const coursesQuery = useQuery({
    queryKey: courseKeys.list(ACTIVE_COURSE_LIST_PARAMS),
    queryFn: fetchAllActiveCourses,
    staleTime: 5 * 60_000,
  });

  const invalidateTemplates = () =>
    queryClient.invalidateQueries({ queryKey: templateKeys.all });

  const preferenceMutation = useMutation({
    mutationFn: ({
      id,
      action,
    }: {
      id: string;
      action: "save" | "unsave" | "dismiss" | "undismiss";
    }) => {
      const fn = {
        save: saveTemplate,
        unsave: unsaveTemplate,
        dismiss: dismissTemplate,
        undismiss: undismissTemplate,
      }[action];

      return fn(id);
    },
    onMutate: ({ id }) => setBusyId(id),
    onSettled: () => setBusyId(null),
    onSuccess: () => void invalidateTemplates(),
    onError: () => setNotice("That change didn't save. Please try again."),
  });

  const copyMutation = useMutation({
    mutationFn: ({
      templateId,
      destination,
    }: {
      templateId: string;
      destination: CopyDestination;
    }) => copyTemplate(templateId, destination),
    onSuccess: () => {
      void invalidateTemplates();
      setUsing(null);
      setNotice("Added to your library. Edit your copy any time.");
    },
  });

  const editMutation = useMutation({
    mutationFn: ({
      copy,
      input,
    }: {
      copy: TemplateCopy;
      input: { title: string; body: string };
    }) =>
      updateTemplateCopy(copy.id, {
        expected_version: copy.version,
        ...input,
      }),
    onSuccess: () => {
      void invalidateTemplates();
      setEditing(null);
    },
  });

  const archiveMutation = useMutation({
    mutationFn: ({
      copy,
      archive,
    }: {
      copy: TemplateCopy;
      archive: boolean;
    }) =>
      archive
        ? archiveTemplateCopy(copy.id, copy.version)
        : restoreTemplateCopy(copy.id, copy.version),
    onMutate: ({ copy }) => setBusyId(copy.id),
    onSettled: () => setBusyId(null),
    onSuccess: () => void invalidateTemplates(),
    onError: (error) =>
      setNotice(
        error instanceof ApiError && error.status === 409
          ? "Another active copy already occupies that destination."
          : "That change didn't save. Please try again.",
      ),
  });

  const templates = useMemo(
    () => (templatesQuery.data?.pages ?? []).flatMap((page) => page.data),
    [templatesQuery.data],
  );
  const copies = useMemo(
    () => (copiesQuery.data?.pages ?? []).flatMap((page) => page.data),
    [copiesQuery.data],
  );

  const categoryTabs = useMemo(() => {
    const byKey = new Map<string, string>();

    for (const template of templates) {
      byKey.set(template.category.key, template.category.name);
    }

    return Array.from(byKey.entries()).map(([key, name]) => ({ key, name }));
  }, [templates]);

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/shelf-books.jpg"
        headingLevel={1}
        tall
        priority
        title="Templates"
        subtitle="Approved, free academic templates you can preview, copy into your own library, and edit. The originals never change."
      />

      {notice ? (
        <Alert variant="info" title="Library update">
          {notice}
        </Alert>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
        <div className="space-y-4">
          <Card>
            <CardHeader className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <CardTitle className="flex items-center gap-2">
                  <LayoutTemplate
                    aria-hidden="true"
                    className="size-5 text-brand-primary"
                  />
                  Template library
                </CardTitle>
                <div className="w-full max-w-xs">
                  <FormField label="Search">
                    {(control) => (
                      <span className="relative block">
                        <Search
                          aria-hidden="true"
                          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
                        />
                        <Input
                          {...control}
                          value={search}
                          onChange={(event) => setSearch(event.target.value)}
                          placeholder="Title starts with…"
                          className="pl-9"
                        />
                      </span>
                    )}
                  </FormField>
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <SavedFilterChip
                  accent="templates"
                  active={savedOnly}
                  onToggle={toggleSaved}
                  describes="templates"
                />
                <p className="text-caption text-text-muted">
                  {savedOnly
                    ? "Showing only the templates you saved."
                    : "Save a template with the bookmark on its card to keep it here."}
                </p>
              </div>

              {categoryTabs.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                  <button
                    type="button"
                    aria-pressed={category === null}
                    onClick={() => setCategory(null)}
                    className={tabClass(category === null)}
                  >
                    All templates
                  </button>
                  {categoryTabs.map((tab) => (
                    <button
                      key={tab.key}
                      type="button"
                      aria-pressed={category === tab.key}
                      onClick={() => setCategory(tab.key)}
                      className={tabClass(category === tab.key)}
                    >
                      {tab.name}
                    </button>
                  ))}
                </div>
              ) : null}
            </CardHeader>
            <CardContent>
              {templatesQuery.isPending ? (
                <div className="grid gap-4 sm:grid-cols-2">
                  <Skeleton className="h-60 rounded-lg" />
                  <Skeleton className="h-60 rounded-lg" />
                </div>
              ) : templatesQuery.isError ? (
                <ErrorState
                  title="Templates could not load"
                  onRetry={() => void templatesQuery.refetch()}
                />
              ) : templates.length === 0 ? (
                <EmptyState
                  icon={LayoutTemplate}
                  title={
                    savedOnly
                      ? "Nothing saved yet"
                      : debouncedSearch !== "" || category !== null
                        ? "No templates match"
                        : "Templates are being curated"
                  }
                  description={
                    savedOnly
                      ? "Turn off the Saved filter to browse the library, then use the bookmark on any template card to keep it here."
                      : debouncedSearch !== "" || category !== null
                        ? "Try a different search or category."
                        : "Approved, free academic templates will appear here as our team publishes them."
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
                    {templates.map((template) => (
                      <TemplateCard
                        key={template.id}
                        template={template}
                        busy={busyId === template.id}
                        onPreview={() => setPreviewing(template)}
                        onUse={() => {
                          copyMutation.reset();
                          setUsing(template);
                        }}
                        onToggleSave={() =>
                          preferenceMutation.mutate({
                            id: template.id,
                            action: template.viewer_state.saved
                              ? "unsave"
                              : "save",
                          })
                        }
                        onToggleDismiss={() =>
                          preferenceMutation.mutate({
                            id: template.id,
                            action: template.viewer_state.dismissed
                              ? "undismiss"
                              : "dismiss",
                          })
                        }
                      />
                    ))}
                  </div>
                  {templatesQuery.hasNextPage ? (
                    <div className="flex justify-center">
                      <Button
                        variant="secondary"
                        isLoading={templatesQuery.isFetchingNextPage}
                        loadingLabel="Loading more"
                        onClick={() => void templatesQuery.fetchNextPage()}
                      >
                        Load more
                      </Button>
                    </div>
                  ) : null}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* My library rail */}
        <Card>
          <CardHeader className="flex flex-wrap items-center justify-between gap-2">
            <CardTitle className="flex items-center gap-2">
              <Library
                aria-hidden="true"
                className="size-5 text-brand-primary"
              />
              My copies
            </CardTitle>
            <label className="flex items-center gap-1.5 text-caption text-text-muted">
              <input
                type="checkbox"
                checked={includeArchived}
                onChange={(event) => setIncludeArchived(event.target.checked)}
                className="size-4 accent-brand-primary"
              />
              Show archived
            </label>
          </CardHeader>
          <CardContent>
            {copiesQuery.isPending ? (
              <div className="space-y-2">
                <Skeleton className="h-16 rounded-md" />
                <Skeleton className="h-16 rounded-md" />
              </div>
            ) : copiesQuery.isError ? (
              <ErrorState
                title="Your copies could not load"
                onRetry={() => void copiesQuery.refetch()}
              />
            ) : copies.length === 0 ? (
              <p className="text-body text-text-muted">
                Templates you use land here as editable copies.
              </p>
            ) : (
              <ul className="space-y-2">
                {copies.map((copy) => (
                  <li
                    key={copy.id}
                    className={cn(
                      "rounded-md border border-border-subtle px-3 py-2.5",
                      copy.archived_at ? "bg-bg-subtle/40" : "bg-bg-subtle/60",
                    )}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <p className="truncate text-body font-medium text-text-primary">
                          {copy.title}
                        </p>
                        <p className="truncate text-caption text-text-muted">
                          {copy.destination === "course" && copy.course
                            ? copy.course.title
                            : "Dashboard"}
                          {copy.archived_at ? " · Archived" : ""}
                        </p>
                      </div>
                      {copy.archived_at ? (
                        <Badge variant="neutral">Archived</Badge>
                      ) : null}
                    </div>
                    <div className="mt-2 flex flex-wrap gap-1">
                      {!copy.archived_at ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => {
                            editMutation.reset();
                            setEditing(copy);
                          }}
                        >
                          <Pencil aria-hidden="true" className="size-4" />
                          Edit
                        </Button>
                      ) : null}
                      <Button
                        variant="ghost"
                        size="sm"
                        disabled={busyId === copy.id}
                        onClick={() =>
                          archiveMutation.mutate({
                            copy,
                            archive: copy.archived_at === null,
                          })
                        }
                      >
                        {copy.archived_at ? (
                          <>
                            <ArchiveRestore
                              aria-hidden="true"
                              className="size-4"
                            />
                            Restore
                          </>
                        ) : (
                          <>
                            <Archive aria-hidden="true" className="size-4" />
                            Archive
                          </>
                        )}
                      </Button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
            {copiesQuery.hasNextPage ? (
              <Button
                variant="ghost"
                size="sm"
                className="mt-2 w-full"
                isLoading={copiesQuery.isFetchingNextPage}
                loadingLabel="Loading"
                onClick={() => void copiesQuery.fetchNextPage()}
              >
                Load more
              </Button>
            ) : null}
          </CardContent>
        </Card>
      </div>

      {previewing ? (
        <TemplatePreviewDialog
          template={previewing}
          onClose={() => setPreviewing(null)}
          onUse={() => {
            copyMutation.reset();
            setUsing(previewing);
            setPreviewing(null);
          }}
        />
      ) : null}

      {using ? (
        <UseTemplateDialog
          template={using}
          courses={coursesQuery.data ?? []}
          busy={copyMutation.isPending}
          error={copyMutation.error}
          onConfirm={(destination) =>
            copyMutation.mutate({ templateId: using.id, destination })
          }
          onClose={() => setUsing(null)}
        />
      ) : null}

      {editing ? (
        <CopyEditorDialog
          key={editing.id}
          copy={editing}
          busy={editMutation.isPending}
          error={editMutation.error}
          onSave={(input) => editMutation.mutate({ copy: editing, input })}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </div>
  );
}

function tabClass(active: boolean): string {
  return cn(
    "rounded-full border px-3 py-1.5 text-caption transition-colors focus-visible:outline-2 focus-visible:outline-brand-focus",
    active
      ? "border-brand-primary/40 bg-bg-interactive text-brand-primary"
      : "border-border-default text-text-secondary hover:border-border-strong",
  );
}
