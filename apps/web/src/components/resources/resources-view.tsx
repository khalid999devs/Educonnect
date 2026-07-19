"use client";

import { Alert, ErrorState } from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import Image from "next/image";
import { useEffect, useMemo, useRef, useState } from "react";

import { listCourses } from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import {
  cancelResourceUpload,
  createLinkResource,
  createResourceDownload,
  deleteResource,
  listResources,
  updateResource,
  type LinkResourceInput,
  type Resource,
  type ResourceUpdateInput,
} from "@/lib/api/resources";
import { courseKeys, resourceKeys } from "@/lib/query-keys";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { AddMaterial } from "./add-material";
import { ResourceDialog } from "./resource-dialog";
import { ResourceTable, type ResourceFilters } from "./resource-table";
import { UploadPanel } from "./upload-panel";
import { useUploads, validateResourceFile } from "./use-uploads";

const DEFAULT_FILTERS: ResourceFilters = {
  search: "",
  kind: "all",
  courseId: "",
  topic: "",
  fileStatus: "all",
};

export function ResourcesView() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<ResourceFilters>(DEFAULT_FILTERS);
  const [debouncedSearch, setDebouncedSearch] = useState("");
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

  const listParams = useMemo(
    () => ({
      search: debouncedSearch === "" ? undefined : debouncedSearch,
      kind: filters.kind === "all" ? undefined : filters.kind,
      courseId: filters.courseId === "" ? undefined : filters.courseId,
      topic: filters.topic === "" ? undefined : filters.topic,
      fileStatus: filters.fileStatus === "all" ? undefined : filters.fileStatus,
    }),
    [debouncedSearch, filters],
  );

  const resourcesQuery = useInfiniteQuery({
    queryKey: resourceKeys.list(listParams),
    queryFn: ({ pageParam }) =>
      listResources({ ...listParams, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) =>
      lastPage.meta.pagination.next_cursor ?? undefined,
  });

  const coursesQuery = useQuery({
    queryKey: courseKeys.list(),
    queryFn: () => listCourses({ status: "active", perPage: 50 }),
    staleTime: 5 * 60_000,
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
      setNotice("Link saved to your library.");
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
        "A download link could not be created — the file may still be processing.",
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

  return (
    <div className="space-y-4">
      <header className="relative min-h-44 overflow-hidden rounded-xl border border-border-default lg:min-h-52">
        <Image
          src="/marketing/library-curve.jpg"
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
          <h1 className="text-h2 text-text-primary">Resources</h1>
          <p className="text-body-lg text-text-secondary">
            Organize your notes, PDFs, links, and class materials in one private
            library.
          </p>
        </div>
      </header>

      {notice ? (
        <Alert variant="info" title="Library update">
          {notice}
        </Alert>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
        <AddMaterial
          courses={coursesQuery.data?.data ?? []}
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

      {resourcesQuery.isError ? (
        <ErrorState
          title="Your library could not load"
          description="Your materials are safe — this is a loading problem, not a data problem."
          onRetry={() => void resourcesQuery.refetch()}
        />
      ) : (
        <ResourceTable
          resources={resources}
          loading={resourcesQuery.isPending}
          filters={filters}
          onFiltersChange={setFilters}
          topics={topics}
          courses={coursesQuery.data?.data ?? []}
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

      <input
        ref={resumeInputRef}
        type="file"
        className="sr-only"
        tabIndex={-1}
        aria-hidden="true"
        accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.md,application/pdf,image/jpeg,image/png,image/webp,text/plain,text/markdown"
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
          courses={coursesQuery.data?.data ?? []}
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
              ? "This resource changed elsewhere — close and try again."
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
