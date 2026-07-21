"use client";

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { Archive, FolderOpen, Library } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import type { ResourceDirectory } from "@/lib/api/resources";

import {
  ARCHIVED_DIRECTORY_REASON,
  DirectoryCard,
  directoryKey,
} from "./directory-card";

export type DirectoryBrowserProps = {
  directories: ResourceDirectory[];
  loading: boolean;
  error: boolean;
  onRetry: () => void;
  selectedKey: string;
  onSelect: (key: string) => void;
  onDropFiles: (key: string, files: File[]) => void;
};

/**
 * The library's shelf: one directory per course, plus the unfiled bucket.
 *
 * Archived courses get their own explicit section. Folding them into the main
 * grid would be worse than useless - their materials would read as lost.
 */
export function DirectoryBrowser({
  directories,
  loading,
  error,
  onRetry,
  selectedKey,
  onSelect,
  onDropFiles,
}: DirectoryBrowserProps) {
  const open = directories.filter(
    (directory) => directory.course?.archive_status !== "archived",
  );
  const archived = directories.filter(
    (directory) => directory.course?.archive_status === "archived",
  );

  return (
    <Card className="motion-safe:animate-fade-up">
      <CardHeader className="mb-3">
        <CardTitle className="flex items-center gap-2.5">
          <IconChip icon={Library} accent="resources" />
          Directories
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {loading ? (
          <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            <Skeleton className="h-18 rounded-md" />
            <Skeleton className="h-18 rounded-md" />
            <Skeleton className="h-18 rounded-md" />
          </div>
        ) : error ? (
          <ErrorState
            title="Your directories could not load"
            description="Your materials are safe. This is a loading problem, not a data problem."
            onRetry={onRetry}
          />
        ) : directories.length === 0 ? (
          <EmptyState
            icon={FolderOpen}
            title="No directories yet"
            description="Add a course in Settings, or add material below to fill the unfiled bucket."
          />
        ) : (
          <>
            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
              {open.map((directory) => {
                const key = directoryKey(directory);

                return (
                  <DirectoryCard
                    key={key}
                    directory={directory}
                    selected={key === selectedKey}
                    onSelect={() => onSelect(key)}
                    onDropFiles={(files) => onDropFiles(key, files)}
                  />
                );
              })}
            </div>

            {archived.length > 0 ? (
              <section
                aria-labelledby="resource-archived-directories"
                className="space-y-2 motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]"
              >
                <div className="flex items-center gap-2">
                  <Archive
                    aria-hidden="true"
                    className="size-4 text-text-muted"
                  />
                  <h3
                    id="resource-archived-directories"
                    className="text-label text-text-secondary"
                  >
                    Archived courses
                  </h3>
                </div>
                <p className="text-caption text-text-muted">
                  {ARCHIVED_DIRECTORY_REASON}
                </p>
                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                  {archived.map((directory) => {
                    const key = directoryKey(directory);

                    return (
                      <DirectoryCard
                        key={key}
                        directory={directory}
                        selected={key === selectedKey}
                        onSelect={() => onSelect(key)}
                      />
                    );
                  })}
                </div>
              </section>
            ) : null}
          </>
        )}
      </CardContent>
    </Card>
  );
}
