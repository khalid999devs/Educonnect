"use client";

import { Badge, cn } from "@educonnect/ui";
import { Archive, FolderOpen, Inbox, Lock } from "lucide-react";
import { useState, type DragEvent } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { UNFILED_COURSE_ID, type ResourceDirectory } from "@/lib/api/resources";

/** The course reference carried by a directory. Archived courses are still
 * listed - their materials must never silently disappear. */
export type DirectoryCourse = NonNullable<ResourceDirectory["course"]>;

/** Stable selection key for a directory: a course public id, or the
 * `course_id=none` sentinel for the single unfiled bucket. */
export function directoryKey(directory: ResourceDirectory): string {
  return directory.kind === "unfiled"
    ? UNFILED_COURSE_ID
    : (directory.course?.id ?? UNFILED_COURSE_ID);
}

export function directoryTitle(directory: ResourceDirectory): string {
  return directory.kind === "unfiled"
    ? "Unfiled"
    : (directory.course?.title ?? "Untitled course");
}

export function isArchivedDirectory(directory: ResourceDirectory): boolean {
  return directory.course?.archive_status === "archived";
}

/** Why an archived directory refuses new material. Shown, never surfaced as a
 * 409 after the fact. */
export const ARCHIVED_DIRECTORY_REASON =
  "This course is archived, so it cannot take new material. Its files stay readable, and restoring the course in Settings reopens it.";

const COUNT_LABEL = (count: number) =>
  `${count} ${count === 1 ? "item" : "items"}`;

export type DirectoryCardProps = {
  directory: ResourceDirectory;
  selected: boolean;
  onSelect: () => void;
  /** Omitted for archived directories, which cannot receive a drop. */
  onDropFiles?: (files: File[]) => void;
};

/**
 * One library directory: a course folder or the unfiled bucket.
 *
 * Files can be dropped straight onto an open directory. Archived directories
 * refuse the drop visibly and say why, rather than accepting the gesture and
 * failing behind it.
 */
export function DirectoryCard({
  directory,
  selected,
  onSelect,
  onDropFiles,
}: DirectoryCardProps) {
  const [dragState, setDragState] = useState<"idle" | "over" | "refused">(
    "idle",
  );
  const archived = isArchivedDirectory(directory);
  const unfiled = directory.kind === "unfiled";
  const title = directoryTitle(directory);
  const canDrop = !archived && onDropFiles !== undefined;

  const onDragOver = (event: DragEvent<HTMLButtonElement>) => {
    if (event.dataTransfer.types.includes("Files") !== true) {
      return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = canDrop ? "copy" : "none";
    setDragState(canDrop ? "over" : "refused");
  };

  const onDrop = (event: DragEvent<HTMLButtonElement>) => {
    event.preventDefault();
    setDragState("idle");

    if (!canDrop) {
      return;
    }

    const files = Array.from(event.dataTransfer.files);

    if (files.length > 0) {
      onDropFiles(files);
    }
  };

  return (
    <button
      type="button"
      aria-pressed={selected}
      onClick={onSelect}
      onDragOver={onDragOver}
      onDragLeave={() => setDragState("idle")}
      onDrop={onDrop}
      className={cn(
        "flex w-full items-start gap-3 rounded-md border bg-bg-surface px-3 py-3 text-left transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
        selected
          ? "border-status-info/40 bg-status-info/12"
          : "border-border-subtle hover:border-border-strong",
        dragState === "over" ? "border-status-info/40 bg-status-info/12" : null,
        dragState === "refused" ? "border-border-strong opacity-70" : null,
      )}
    >
      <IconChip
        icon={archived ? Archive : unfiled ? Inbox : FolderOpen}
        accent={archived ? "settings" : "resources"}
      />
      <span className="min-w-0 flex-1">
        <span className="flex items-center gap-2">
          <span className="min-w-0 flex-1 truncate text-body font-medium text-text-primary">
            {title}
          </span>
          {archived ? <Badge variant="neutral">Archived</Badge> : null}
        </span>
        <span className="mt-0.5 block truncate text-caption text-text-muted">
          {unfiled ? (
            "Not filed under a course"
          ) : (
            <>
              {directory.course?.code ? `${directory.course.code} · ` : ""}
              <span className="tabular-nums">
                {COUNT_LABEL(directory.resource_count)}
              </span>
            </>
          )}
        </span>
        {unfiled ? (
          <span className="mt-0.5 block text-caption tabular-nums text-text-secondary">
            {COUNT_LABEL(directory.resource_count)}
          </span>
        ) : null}
        {dragState === "refused" ? (
          <span className="mt-1 flex items-start gap-1.5 text-caption text-text-secondary">
            <Lock aria-hidden="true" className="mt-0.5 size-3 shrink-0" />
            {ARCHIVED_DIRECTORY_REASON}
          </span>
        ) : null}
      </span>
    </button>
  );
}
