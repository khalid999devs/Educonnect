"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  EmptyState,
  FormField,
  Input,
  Select,
  Skeleton,
} from "@educonnect/ui";
import {
  Download,
  ExternalLink,
  FileText,
  FileUp,
  Folder,
  Library,
  Link2,
  Pencil,
  Search,
  Trash2,
} from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import type { Resource, ResourceListParams } from "@/lib/api/resources";

const TYPE_LABELS: Record<string, string> = {
  "application/pdf": "PDF",
  "image/jpeg": "JPEG",
  "image/png": "PNG",
  "image/webp": "WebP",
  "text/plain": "Text",
  "text/markdown": "Markdown",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
    "Word",
  "application/vnd.openxmlformats-officedocument.presentationml.presentation":
    "PowerPoint",
};

export function resourceTypeLabel(resource: Resource): string {
  if (resource.kind === "link") {
    return "Link";
  }

  const mime =
    resource.file?.verified_mime_type ?? resource.file?.declared_mime_type;

  return (mime && TYPE_LABELS[mime]) ?? "File";
}

function addedLabel(iso: string): string {
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(iso));
}

function statusBadge(resource: Resource) {
  if (resource.kind === "link") {
    return null;
  }

  switch (resource.file?.status) {
    case "ready":
      return <Badge variant="success">Ready</Badge>;
    case "pending":
      return <Badge variant="warning">Upload unfinished</Badge>;
    case "deletion_pending":
      return <Badge variant="neutral">Deleting…</Badge>;
    default:
      return null;
  }
}

/** The course dimension is the directory itself, so it is deliberately absent
 * here: filtering by course inside a course directory is a contradiction. */
export type ResourceFilters = Required<
  Pick<ResourceListParams, "kind" | "fileStatus">
> & {
  search: string;
  topic: string;
};

export type ResourceTableProps = {
  /** The open directory, named in the header and the empty state. */
  directoryName: string;
  resources: Resource[];
  loading: boolean;
  filters: ResourceFilters;
  onFiltersChange: (filters: ResourceFilters) => void;
  topics: string[];
  hasNextPage: boolean;
  loadingMore: boolean;
  onLoadMore: () => void;
  onDownload: (resource: Resource) => void;
  downloadingId: string | null;
  onEdit: (resource: Resource) => void;
  onDelete: (resource: Resource) => void;
  onResume: (resource: Resource) => void;
  onCancelPending: (resource: Resource) => void;
};

/** One directory's contents: search + filters, honest lifecycle states, and
 * per-row recovery for unfinished uploads. Rows stack below md. */
export function ResourceTable({
  directoryName,
  resources,
  loading,
  filters,
  onFiltersChange,
  topics,
  hasNextPage,
  loadingMore,
  onLoadMore,
  onDownload,
  downloadingId,
  onEdit,
  onDelete,
  onResume,
  onCancelPending,
}: ResourceTableProps) {
  const filtersActive =
    filters.search !== "" ||
    filters.kind !== "all" ||
    filters.topic !== "" ||
    filters.fileStatus !== "all";

  const rowActions = (resource: Resource) => (
    <div className="flex flex-wrap items-center justify-end gap-1">
      {resource.kind === "link" && resource.url ? (
        <Button
          variant="ghost"
          size="sm"
          aria-label={`Open link "${resource.title}"`}
          onClick={() => {
            window.open(resource.url as string, "_blank", "noopener");
          }}
        >
          <ExternalLink aria-hidden="true" className="size-4" />
        </Button>
      ) : null}
      {resource.kind === "file" && resource.file?.status === "ready" ? (
        <Button
          variant="ghost"
          size="sm"
          aria-label={`Download "${resource.title}"`}
          isLoading={downloadingId === resource.id}
          loadingLabel="Preparing download"
          onClick={() => onDownload(resource)}
        >
          <Download aria-hidden="true" className="size-4" />
        </Button>
      ) : null}
      {resource.kind === "file" && resource.file?.status === "pending" ? (
        <>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => onResume(resource)}
          >
            <FileUp aria-hidden="true" className="size-4" />
            <span className="ml-1">Finish upload</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onCancelPending(resource)}
          >
            Cancel
          </Button>
        </>
      ) : null}
      {resource.file?.status !== "deletion_pending" ? (
        <>
          <Button
            variant="ghost"
            size="sm"
            aria-label={`Edit "${resource.title}"`}
            onClick={() => onEdit(resource)}
          >
            <Pencil aria-hidden="true" className="size-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            aria-label={`Delete "${resource.title}"`}
            className="text-status-error"
            onClick={() => onDelete(resource)}
          >
            <Trash2 aria-hidden="true" className="size-4" />
          </Button>
        </>
      ) : null}
    </div>
  );

  return (
    <Card className="motion-safe:animate-fade-up motion-safe:[animation-delay:240ms]">
      <CardHeader className="space-y-3">
        <CardTitle className="flex items-center gap-2.5">
          <IconChip icon={Library} accent="resources" />
          Materials in {directoryName}
        </CardTitle>

        <div className="grid gap-3 md:grid-cols-3">
          <FormField label="Search">
            {(control) => (
              <span className="relative block">
                <Search
                  aria-hidden="true"
                  className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
                />
                <Input
                  {...control}
                  value={filters.search}
                  onChange={(event) =>
                    onFiltersChange({ ...filters, search: event.target.value })
                  }
                  placeholder="Title starts with…"
                  className="pl-9"
                />
              </span>
            )}
          </FormField>
          <FormField label="Kind">
            {(control) => (
              <Select
                {...control}
                value={filters.kind}
                onChange={(event) =>
                  onFiltersChange({
                    ...filters,
                    kind: event.target.value as ResourceFilters["kind"],
                  })
                }
              >
                <option value="all">All kinds</option>
                <option value="file">Files</option>
                <option value="link">Links</option>
              </Select>
            )}
          </FormField>
          <FormField label="File state">
            {(control) => (
              <Select
                {...control}
                value={filters.fileStatus}
                onChange={(event) =>
                  onFiltersChange({
                    ...filters,
                    fileStatus: event.target
                      .value as ResourceFilters["fileStatus"],
                  })
                }
              >
                <option value="all">All states</option>
                <option value="ready">Ready</option>
                <option value="pending">Upload unfinished</option>
                <option value="deletion_pending">Deleting</option>
              </Select>
            )}
          </FormField>
        </div>

        {topics.length > 0 ? (
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-caption font-medium text-text-muted">
              Collections:
            </span>
            {topics.map((topic) => (
              <button
                key={topic}
                type="button"
                aria-pressed={filters.topic === topic}
                onClick={() =>
                  onFiltersChange({
                    ...filters,
                    topic: filters.topic === topic ? "" : topic,
                  })
                }
                className={cn(
                  "flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-caption transition-colors focus-visible:outline-2 focus-visible:outline-brand-focus",
                  filters.topic === topic
                    ? "border-brand-primary/40 bg-bg-interactive text-brand-primary"
                    : "border-border-default text-text-secondary hover:border-border-strong",
                )}
              >
                <Folder aria-hidden="true" className="size-3.5" />
                {topic}
              </button>
            ))}
          </div>
        ) : null}
      </CardHeader>

      <CardContent className="space-y-3">
        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-14 rounded-md" />
            <Skeleton className="h-14 rounded-md" />
            <Skeleton className="h-14 rounded-md" />
          </div>
        ) : resources.length === 0 ? (
          <EmptyState
            icon={Library}
            title={
              filtersActive
                ? "Nothing matches these filters"
                : `${directoryName} is empty`
            }
            description={
              filtersActive
                ? "Clear a filter or search differently."
                : "Upload a file or save a link above, or drag files straight onto a directory."
            }
          />
        ) : (
          <>
            {/* md+: table layout */}
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full border-collapse text-left">
                <thead>
                  <tr className="border-b border-border-default text-caption uppercase tracking-wide text-text-muted">
                    <th scope="col" className="py-2 pr-3 font-medium">
                      Name
                    </th>
                    <th scope="col" className="py-2 pr-3 font-medium">
                      Type
                    </th>
                    <th scope="col" className="py-2 pr-3 font-medium">
                      Topic
                    </th>
                    <th scope="col" className="py-2 pr-3 font-medium">
                      Added
                    </th>
                    <th scope="col" className="py-2 pr-3 font-medium">
                      Status
                    </th>
                    <th scope="col" className="py-2 text-right font-medium">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {resources.map((resource) => (
                    <tr
                      key={resource.id}
                      className="border-b border-border-subtle transition-colors hover:bg-bg-subtle/60"
                    >
                      <td className="max-w-64 py-2.5 pr-3">
                        <span className="flex items-center gap-2.5">
                          <IconChip
                            icon={resource.kind === "link" ? Link2 : FileText}
                            accent="resources"
                            size="sm"
                          />
                          <span className="min-w-0">
                            <span className="block truncate text-body font-medium text-text-primary">
                              {resource.title}
                            </span>
                            <span className="block truncate text-caption text-text-muted">
                              {resource.kind === "link"
                                ? resource.url
                                : resource.file?.original_name}
                            </span>
                          </span>
                        </span>
                      </td>
                      <td className="py-2.5 pr-3 text-body text-text-secondary">
                        {resourceTypeLabel(resource)}
                      </td>
                      <td className="max-w-40 truncate py-2.5 pr-3 text-body text-text-secondary">
                        {resource.topic ?? "-"}
                      </td>
                      <td className="py-2.5 pr-3 text-body tabular-nums text-text-secondary">
                        {addedLabel(resource.created_at)}
                      </td>
                      <td className="py-2.5 pr-3">{statusBadge(resource)}</td>
                      <td className="py-2.5">{rowActions(resource)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* <md: stacked rows */}
            <ul className="space-y-2 md:hidden">
              {resources.map((resource) => (
                <li
                  key={resource.id}
                  className="rounded-md border border-border-subtle bg-bg-surface px-3 py-2.5 transition-colors hover:border-border-strong"
                >
                  <div className="flex items-center gap-2.5">
                    <IconChip
                      icon={resource.kind === "link" ? Link2 : FileText}
                      accent="resources"
                      size="sm"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-body font-medium text-text-primary">
                        {resource.title}
                      </span>
                      <span className="block truncate text-caption text-text-muted">
                        {resourceTypeLabel(resource)}
                        {resource.topic ? ` · ${resource.topic}` : ""}
                      </span>
                    </span>
                    {statusBadge(resource)}
                  </div>
                  <div className="mt-2">{rowActions(resource)}</div>
                </li>
              ))}
            </ul>

            {hasNextPage ? (
              <div className="flex justify-center">
                <Button
                  variant="secondary"
                  size="md"
                  isLoading={loadingMore}
                  loadingLabel="Loading more"
                  onClick={onLoadMore}
                >
                  Load more
                </Button>
              </div>
            ) : null}
          </>
        )}
      </CardContent>
    </Card>
  );
}
