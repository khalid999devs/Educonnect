"use client";

import {
  Alert,
  Button,
  cn,
  ErrorState,
  FormField,
  Input,
  Skeleton,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, FolderOpen, FolderPlus, Inbox } from "lucide-react";
import { useState, type FormEvent } from "react";

import { SECTION_ACCENT } from "@/components/shell/section-accent";
import { createCourse } from "@/lib/api/courses";
import {
  listResourceDirectories,
  type ResourceDirectory,
} from "@/lib/api/resources";
import { courseKeys, resourceKeys } from "@/lib/query-keys";

/** `null` is the unfiled bucket, which is a real destination and not an
 * absence of one. */
export type DirectorySelection = string | null;

export type DirectoryPickerProps = {
  value: DirectorySelection;
  onChange: (courseId: DirectorySelection) => void;
  disabled?: boolean;
};

const ARCHIVED_REASON = "Archived courses can't take new material.";

function directoryLabel(directory: ResourceDirectory): string {
  if (directory.kind === "unfiled" || !directory.course) {
    return "Unfiled";
  }

  return directory.course.code
    ? `${directory.course.code} · ${directory.course.title}`
    : directory.course.title;
}

/**
 * Step three: auto-file it.
 *
 * A resource directory IS a course, so the roster comes from
 * `GET /resources/directories` (which already carries per-directory counts)
 * and creating one writes through `POST /courses`. Archived directories are
 * shown but disabled with the reason up front, rather than surfacing a 409
 * after the student has already committed.
 */
export function DirectoryPicker({
  value,
  onChange,
  disabled = false,
}: DirectoryPickerProps) {
  const queryClient = useQueryClient();
  const [creating, setCreating] = useState(false);
  const [title, setTitle] = useState("");
  const [code, setCode] = useState("");

  const directoriesQuery = useQuery({
    queryKey: resourceKeys.directories(),
    queryFn: listResourceDirectories,
    staleTime: 60_000,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createCourse({
        title: title.trim(),
        code: code.trim() === "" ? null : code.trim(),
      }),
    onSuccess: (course) => {
      void queryClient.invalidateQueries({ queryKey: resourceKeys.all });
      void queryClient.invalidateQueries({ queryKey: courseKeys.all });
      setTitle("");
      setCode("");
      setCreating(false);
      onChange(course.id);
    },
  });

  const submitCourse = (event: FormEvent) => {
    event.preventDefault();
    createMutation.mutate();
  };

  if (directoriesQuery.isPending) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-11 rounded-md" />
        <Skeleton className="h-11 rounded-md" />
      </div>
    );
  }

  if (directoriesQuery.isError) {
    return (
      <ErrorState
        title="Your directories couldn't load"
        onRetry={() => void directoriesQuery.refetch()}
      />
    );
  }

  const directories = directoriesQuery.data;
  const accent = SECTION_ACCENT.resources;

  return (
    <div className="space-y-3">
      <ul
        role="radiogroup"
        aria-label="Where should this be filed"
        className="grid gap-2 sm:grid-cols-2"
      >
        {directories.map((directory) => {
          const courseId = directory.course?.id ?? null;
          const archived = directory.course?.archive_status === "archived";
          const selected = value === courseId;
          const Icon = directory.kind === "unfiled" ? Inbox : FolderOpen;

          return (
            <li key={courseId ?? "unfiled"}>
              <button
                type="button"
                role="radio"
                aria-checked={selected}
                disabled={disabled || archived}
                title={archived ? ARCHIVED_REASON : undefined}
                onClick={() => onChange(courseId)}
                className={cn(
                  "flex w-full items-center gap-2.5 rounded-md border p-2.5 text-left transition-colors",
                  "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                  "disabled:cursor-not-allowed disabled:opacity-55",
                  selected
                    ? cn("bg-bg-elevated", accent.ring)
                    : "border-border-subtle bg-bg-surface hover:border-border-strong",
                )}
              >
                <span
                  className={cn(
                    "flex size-8 shrink-0 items-center justify-center rounded-md",
                    accent.chip,
                  )}
                >
                  <Icon
                    aria-hidden="true"
                    className={cn("size-4", accent.icon)}
                  />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-body text-text-primary">
                    {directoryLabel(directory)}
                    {archived ? " (archived)" : ""}
                  </span>
                  <span className="text-caption tabular-nums text-text-muted">
                    {directory.resource_count}{" "}
                    {directory.resource_count === 1 ? "item" : "items"}
                  </span>
                </span>
                {selected ? (
                  <Check
                    aria-hidden="true"
                    className={cn("size-4 shrink-0", accent.icon)}
                  />
                ) : null}
              </button>
            </li>
          );
        })}
      </ul>

      {creating ? (
        <form
          onSubmit={submitCourse}
          className="space-y-3 rounded-md border border-border-subtle bg-bg-subtle/60 p-3"
        >
          <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem]">
            <FormField label="Directory name" required>
              {(control) => (
                <Input
                  {...control}
                  value={title}
                  maxLength={160}
                  autoFocus
                  onChange={(event) => setTitle(event.target.value)}
                  placeholder="e.g. Distributed Systems"
                />
              )}
            </FormField>
            <FormField label="Code" hint="Optional">
              {(control) => (
                <Input
                  {...control}
                  value={code}
                  maxLength={32}
                  onChange={(event) => setCode(event.target.value)}
                  placeholder="e.g. CS-451"
                />
              )}
            </FormField>
          </div>
          {createMutation.error ? (
            <Alert variant="error" title="Couldn't create that directory">
              {createMutation.error.message}
            </Alert>
          ) : null}
          <div className="flex flex-wrap gap-2">
            <Button
              type="submit"
              size="sm"
              isLoading={createMutation.isPending}
              loadingLabel="Creating"
              disabled={title.trim() === ""}
            >
              Create and file here
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                createMutation.reset();
                setCreating(false);
              }}
            >
              Cancel
            </Button>
          </div>
        </form>
      ) : (
        <Button
          variant="secondary"
          size="sm"
          disabled={disabled}
          onClick={() => setCreating(true)}
        >
          <FolderPlus aria-hidden="true" className="size-4" />
          <span className="ml-1.5">New directory</span>
        </Button>
      )}
    </div>
  );
}
