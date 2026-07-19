"use client";

import {
  Alert,
  Button,
  Dialog,
  FormField,
  Input,
  Select,
  Textarea,
} from "@educonnect/ui";
import { useState, type FormEvent } from "react";

import type { Course } from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import type { Resource, ResourceUpdateInput } from "@/lib/api/resources";

export type ResourceDialogProps = {
  open: boolean;
  resource: Resource;
  courses: Course[];
  busy: boolean;
  onUpdate: (resource: Resource, input: ResourceUpdateInput) => void;
  onClose: () => void;
  error: ApiError | Error | null;
};

/** Edit a resource's metadata; only link resources may change their URL -
 * a private file keeps its object identity (resources contract). */
export function ResourceDialog({
  open,
  resource,
  courses,
  busy,
  onUpdate,
  onClose,
  error,
}: ResourceDialogProps) {
  const [title, setTitle] = useState(resource.title);
  const [description, setDescription] = useState(resource.description ?? "");
  const [topic, setTopic] = useState(resource.topic ?? "");
  const [courseId, setCourseId] = useState(resource.course?.id ?? "");
  const [url, setUrl] = useState(resource.url ?? "");

  const apiError = error instanceof ApiError ? error : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();

    const shared = {
      expected_version: resource.version,
      title: title.trim(),
      description: description.trim() === "" ? null : description.trim(),
      topic: topic.trim() === "" ? null : topic.trim(),
      course_id: courseId === "" ? null : courseId,
    };

    onUpdate(
      resource,
      resource.kind === "link"
        ? { kind: "link", ...shared, url: url.trim() }
        : { kind: "file", ...shared },
    );
  };

  return (
    <Dialog
      open={open}
      onClose={busy ? () => undefined : onClose}
      title={resource.kind === "link" ? "Edit link" : "Edit file details"}
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button
            type="submit"
            form="resource-dialog-form"
            isLoading={busy}
            loadingLabel="Saving"
            disabled={
              title.trim() === "" ||
              (resource.kind === "link" && url.trim() === "")
            }
          >
            Save changes
          </Button>
        </>
      }
    >
      <form id="resource-dialog-form" onSubmit={submit} className="space-y-4">
        {error && !apiError ? (
          <Alert variant="error" title="Something went wrong">
            {error.message}
          </Alert>
        ) : null}
        {apiError && apiError.status === 409 ? (
          <Alert variant="error" title="This resource changed elsewhere">
            Close the dialog to load the latest version, then edit again.
          </Alert>
        ) : null}

        <FormField label="Title" required error={apiError?.fieldError("title")}>
          {(control) => (
            <Input
              {...control}
              value={title}
              maxLength={160}
              onChange={(event) => setTitle(event.target.value)}
            />
          )}
        </FormField>

        {resource.kind === "link" ? (
          <FormField
            label="Link"
            required
            hint="HTTPS links only"
            error={apiError?.fieldError("url")}
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
        ) : (
          <p className="text-caption text-text-muted">
            {resource.file?.original_name}. The stored file itself cannot be
            swapped; add a new resource for a different file.
          </p>
        )}

        <FormField
          label="Description"
          error={apiError?.fieldError("description")}
        >
          {(control) => (
            <Textarea
              {...control}
              value={description}
              maxLength={2000}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="Optional details"
            />
          )}
        </FormField>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            label="Topic"
            hint="Groups your library into collections"
            error={apiError?.fieldError("topic")}
          >
            {(control) => (
              <Input
                {...control}
                value={topic}
                maxLength={120}
                onChange={(event) => setTopic(event.target.value)}
                placeholder="e.g. Algorithms"
              />
            )}
          </FormField>
          <FormField label="Course" error={apiError?.fieldError("course_id")}>
            {(control) => (
              <Select
                {...control}
                value={courseId}
                onChange={(event) => setCourseId(event.target.value)}
              >
                <option value="">No course</option>
                {courses.map((course) => (
                  <option key={course.id} value={course.id}>
                    {course.code ? `${course.code} · ` : ""}
                    {course.title}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        </div>
      </form>
    </Dialog>
  );
}
