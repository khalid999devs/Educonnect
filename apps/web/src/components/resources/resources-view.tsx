"use client";

import { Alert, Badge, ErrorState } from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { Archive, FolderOpen, Inbox, Lock } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { PageCover } from "@/components/shared/page-cover";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { ApiError } from "@/lib/api/http";
import {
  cancelResourceUpload,
  createLinkResource,
  createResourceDownload,
  deleteResource,
  listResourceDirectories,
  listResources,
  updateResource,
  RESOURCE_FILE_ACCEPT,
  UNFILED_COURSE_ID,
  type LinkResourceInput,
  type Resource,
  type ResourceDirectory,
  type ResourceUpdateInput,
} from "@/lib/api/resources";
import { resourceKeys } from "@/lib/query-keys";

import { AddMaterial } from "./add-material";
import {
  ARCHIVED_DIRECTORY_REASON,
  directoryKey,
  directoryTitle,
  isArchivedDirectory,
  type DirectoryCourse,
} from "./directory-card";
import { DirectoryBrowser } from "./directory-browser";
import { ResourceDialog } from "./resource-dialog";
import { ResourceTable, type ResourceFilters } from "./resource-table";
import { UploadPanel } from "./upload-panel";
import { useUploads, validateResourceFile } from "./use-uploads";

const DEFAULT_FILTERS: ResourceFilters = {
  search: "",
  kind: "all",
  topic: "",
  fileStatus: "all",
};

/**
 * The private library, presented as one directory per course plus a single
 * unfiled bucket.
 *
 * The course roster comes from `GET /resources/directories`, which is bounded
 * by the owner's course list and therefore deliberately uncursored. It also
 * returns archived courses that still hold resources - the reason the archived
 * section exists at all, and the reason no list here silently truncates.
 */
export function ResourcesView() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<ResourceFilters>(DEFAULT_FILTERS);
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [selectedKey, setSelectedKey] = useState<string | null>(null);
  const [editing, setEditing] = useState<Resource | null>(null);
  const [deleting, setDeleting] = useState<Resource | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [linkSavedCount, setLinkSavedCount] = useState(0);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);
  const resumeTargetRef = useRef<Resource | null>(null);
  const resumeInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const timer = window.setTimeout(
      () => setDebouncedSearch(filters.search.trim()),
      300,
    );

    return () => window.clearTimeout(timer);
  }, [filters.search]);

  useEffect(() => {
    if (notice === null) {
      return;
    }

    const timer = window.setTimeout(() => setNotice(null), 8_000);

    return () => window.clearTimeout(timer);
  }, [notice]);

  const directoriesQuery = useQuery({
    queryKey: resourceKeys.directories(),
    queryFn: () => listResourceDirectories(),
    staleTime: 60_000,
  });

  const directories = useMemo<ResourceDirectory[]>(
    () => directoriesQuery.data ?? [],
    [directoriesQuery.data],
  );

  /* The endpoint always returns the unfiled bucket last, so the first entry is
     the first course directory when one exists. Deriving the fallback rather
     than storing it keeps the selection correct across refetches. */
  const firstDirectory = directories[0];
  const activeKey =
    selectedKey ??
    (firstDirectory ? directoryKey(firstDirectory) : UNFILED_COURSE_ID);

  const currentDirectory =
    directories.find((directory) => directoryKey(directory) === activeKey) ??
    null;

  const currentArchived =
    currentDirectory !== null && isArchivedDirectory(currentDirectory);

  const courses = useMemo<DirectoryCourse[]>(
    () =>
      directories
        .map((directory) => directory.course)
        .filter((course): course is DirectoryCourse => course !== null),
    [directories],
  );

  const listParams = useMemo(
    () => ({
      courseId: activeKey,
      search: debouncedSearch === "" ? undefined : debouncedSearch,
      kind: filters.kind === "all" ? undefined : filters.kind,
      topic: filters.topic === "" ? undefined : filters.topic,
      fileStatus: filters.fileStatus === "all" ? undefined : filters.fileStatus,
    }),
    [activeKey, debouncedSearch, filters],
  );

  const resourcesQuery = useInfiniteQuery({
    queryKey: resourceKeys.list(listParams),
    queryFn: ({ pageParam }) =>
      listResources({ ...listParams, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) =>
      lastPage.meta.pagination.next_cursor ?? undefined,
  });

  const invalidateResources = () => {
    void queryClient.invalidateQueries({ queryKey: resourceKeys.all });
  };

  const uploads = useUploads({ onSettled: invalidateResources });

  const linkMutation = useMutation({
    mutationFn: (input: LinkResourceInput) => createLinkResource(input),
    onSuccess: () => {
      invalidateResources();
      setLinkSavedCount((count) => count + 1);
      setNotice("Link saved to this directory.");
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({
      resource,
      input,
    }: {
      resource: Resource;
      input: ResourceUpdateInput;
    }) => updateResource(resource.id, input),
    onSuccess: () => {
      invalidateResources();
      setEditing(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: ({ resource }: { resource: Resource }) =>
      deleteResource(resource.id, resource.version),
    onSuccess: (_, { resource }) => {
      invalidateResources();
      setDeleting(null);
      setNotice(
        resource.kind === "link"
          ? "Link removed."
          : "File deletion started. The entry shows Deleting until cleanup finishes.",
      );
    },
  });

  const cancelPendingMutation = useMutation({
    mutationFn: ({ resource }: { resource: Resource }) =>
      cancelResourceUpload(resource.id, resource.version),
    onSuccess: () => {
      invalidateResources();
      setNotice("Unfinished upload cancelled. Cleanup has started.");
    },
    onError: () => {
      invalidateResources();
      setNotice("The upload could not be cancelled. Please try again.");
    },
  });

  const downloadMutation = useMutation({
    mutationFn: (resource: Resource) => createResourceDownload(resource.id),
    onMutate: (resource) => setDownloadingId(resource.id),
    onSettled: () => setDownloadingId(null),
    onSuccess: (grant) => {
      /* Short-lived private URL: opened directly, never logged or stored. */
      const anchor = document.createElement("a");

      anchor.href = grant.url;
      anchor.target = "_blank";
      anchor.rel = "noopener";
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
    },
    onError: () => {
      setNotice(
        "A download link could not be created. The file may still be processing.",
      );
    },
  });

  const resources = useMemo(
    () => (resourcesQuery.data?.pages ?? []).flatMap((page) => page.data),
    [resourcesQuery.data],
  );

  const topics = useMemo(() => {
    const seen = new Set<string>();

    for (const resource of resources) {
      if (resource.topic) {
        seen.add(resource.topic);
      }
    }

    if (filters.topic !== "") {
      seen.add(filters.topic);
    }

    return Array.from(seen).sort((a, b) => a.localeCompare(b));
  }, [resources, filters.topic]);

  const uploadInto = (courseId: string | null, files: File[]) => {
    for (const file of files) {
      const invalid = validateResourceFile(file);

      if (invalid) {
        setNotice(`${file.name}: ${invalid.message}`);
        continue;
      }

      void uploads.startUpload(file, { courseId, topic: null });
    }
  };

  const onDirectoryDrop = (key: string, files: File[]) => {
    setSelectedKey(key);
    uploadInto(key === UNFILED_COURSE_ID ? null : key, files);
  };

  const startResume = (resource: Resource) => {
    resumeTargetRef.current = resource;
    resumeInputRef.current?.click();
  };

  const onResumeFilePicked = (file: File | null) => {
    const target = resumeTargetRef.current;

    resumeTargetRef.current = null;

    if (!target || !file) {
      return;
    }

    const invalid = validateResourceFile(file);

    if (invalid) {
      setNotice(`${file.name}: ${invalid.message}`);
      return;
    }

    void uploads.resumeUpload(target, file);
  };

  const directoryName = currentDirectory
    ? directoryTitle(currentDirectory)
    : "Unfiled";

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/library-curve.jpg"
        headingLevel={1}
        tall
        priority
        title="One private library, filed by course"
        subtitle="Every course keeps its own directory. Anything without a course waits in Unfiled until you file it."
      />

      {notice ? (
        <Alert variant="info" title="Library update">
          {notice}
        </Alert>
      ) : null}

      <DirectoryBrowser
        directories={directories}
        loading={directoriesQuery.isPending}
        error={directoriesQuery.isError}
        onRetry={() => void directoriesQuery.refetch()}
        selectedKey={activeKey}
        onSelect={(key) => {
          setSelectedKey(key);
          setFilters(DEFAULT_FILTERS);
          setDebouncedSearch("");
        }}
        onDropFiles={onDirectoryDrop}
      />

      <div className="flex flex-wrap items-center gap-2.5 motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]">
        <IconChip
          icon={
            currentArchived
              ? Archive
              : currentDirectory?.kind === "unfiled"
                ? Inbox
                : FolderOpen
          }
          accent={currentArchived ? "settings" : "resources"}
          size="lg"
        />
        <div className="min-w-0">
          <h2 className="truncate text-h4 text-text-primary">
            {directoryName}
          </h2>
          <p className="text-caption tabular-nums text-text-muted">
            {currentDirectory
              ? `${currentDirectory.resource_count} ${
                  currentDirectory.resource_count === 1 ? "item" : "items"
                }`
              : "0 items"}
          </p>
        </div>
        {currentArchived ? <Badge variant="neutral">Archived</Badge> : null}
      </div>

      {currentArchived ? (
        <Alert variant="info" title="This directory is read only">
          {ARCHIVED_DIRECTORY_REASON}
        </Alert>
      ) : (
        <div className="grid gap-4 motion-safe:animate-fade-up motion-safe:[animation-delay:160ms] xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
          <AddMaterial
            key={activeKey}
            courses={courses}
            directoryCourseId={
              currentDirectory?.kind === "course"
                ? (currentDirectory.course?.id ?? null)
                : null
            }
            directoryName={directoryName}
            onUploadFiles={(files, meta) => {
              for (const file of files) {
                void uploads.startUpload(file, meta);
              }
            }}
            onCreateLink={(input) => linkMutation.mutate(input)}
            linkBusy={linkMutation.isPending}
            linkError={linkMutation.error}
            linkSavedCount={linkSavedCount}
          />
          <UploadPanel
            jobs={uploads.jobs}
            onRetry={(job) => void uploads.retryJob(job)}
            onCancel={(job) => void uploads.cancelJob(job)}
            onDismiss={uploads.dismissJob}
          />
        </div>
      )}

      {resourcesQuery.isError ? (
        <ErrorState
          title="This directory could not load"
          description="Your materials are safe. This is a loading problem, not a data problem."
          onRetry={() => void resourcesQuery.refetch()}
        />
      ) : (
        <ResourceTable
          directoryName={directoryName}
          resources={resources}
          loading={resourcesQuery.isPending}
          filters={filters}
          onFiltersChange={setFilters}
          topics={topics}
          hasNextPage={resourcesQuery.hasNextPage}
          loadingMore={resourcesQuery.isFetchingNextPage}
          onLoadMore={() => void resourcesQuery.fetchNextPage()}
          onDownload={(resource) => downloadMutation.mutate(resource)}
          downloadingId={downloadingId}
          onEdit={(resource) => {
            updateMutation.reset();
            setEditing(resource);
          }}
          onDelete={(resource) => {
            deleteMutation.reset();
            setDeleting(resource);
          }}
          onResume={startResume}
          onCancelPending={(resource) =>
            cancelPendingMutation.mutate({ resource })
          }
        />
      )}

      <p className="flex items-center gap-1.5 text-caption text-text-muted">
        <Lock aria-hidden="true" className="size-3 shrink-0" />
        Your library is private to your account. Saved collections arrive as
        their own subsection.
      </p>

      <input
        ref={resumeInputRef}
        type="file"
        className="sr-only"
        tabIndex={-1}
        aria-hidden="true"
        accept={RESOURCE_FILE_ACCEPT}
        onChange={(event) => {
          onResumeFilePicked(event.target.files?.[0] ?? null);
          event.target.value = "";
        }}
      />

      {editing ? (
        <ResourceDialog
          key={editing.id}
          open
          resource={editing}
          courses={courses}
          busy={updateMutation.isPending}
          error={updateMutation.error}
          onUpdate={(resource, input) =>
            updateMutation.mutate({ resource, input })
          }
          onClose={() => setEditing(null)}
        />
      ) : null}

      {deleting ? (
        <ConfirmDialog
          open
          title={
            deleting.kind === "link" ? "Remove this link?" : "Delete this file?"
          }
          description={
            deleting.kind === "link"
              ? `"${deleting.title}" will be removed from your library immediately.`
              : `"${deleting.title}" will be deleted and its private file cleaned up. The entry shows Deleting until that finishes.`
          }
          confirmLabel="Delete"
          busy={deleteMutation.isPending}
          error={
            deleteMutation.error instanceof ApiError &&
            deleteMutation.error.status === 409
              ? "This resource changed elsewhere. Close and try again."
              : (deleteMutation.error?.message ?? null)
          }
          onConfirm={() => deleteMutation.mutate({ resource: deleting })}
          onCancel={() => {
            deleteMutation.reset();
            setDeleting(null);
          }}
        />
      ) : null}
    </div>
  );
}
