"use client";

import {
  Alert,
  Badge,
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
import type {
  CopyDestination,
  Template,
  TemplateCopy,
} from "@/lib/api/templates";

/** Read-only preview of a template's latest published version. */
export function TemplatePreviewDialog({
  template,
  onClose,
  onUse,
}: {
  template: Template;
  onClose: () => void;
  onUse: () => void;
}) {
  return (
    <Dialog
      open
      onClose={onClose}
      title={template.title}
      description={template.category.name}
      size="lg"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Close
          </Button>
          <Button onClick={onUse} disabled={template.latest_version === null}>
            Use template
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <p className="text-body text-text-secondary">{template.summary}</p>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="success">Approved · Free</Badge>
          {template.latest_version ? (
            <Badge variant="neutral">
              Version {template.latest_version.number} ·{" "}
              {template.latest_version.format}
            </Badge>
          ) : null}
        </div>
        {template.latest_version ? (
          <pre className="max-h-96 overflow-auto whitespace-pre-wrap rounded-md border border-border-subtle bg-bg-subtle/60 p-3 font-mono text-caption text-text-primary">
            {template.latest_version.body}
          </pre>
        ) : (
          <p className="text-body text-text-muted">
            This template has no published version yet.
          </p>
        )}
        <p className="text-caption text-text-muted">
          {template.provenance} · Using it creates your own independent editable
          copy. The original never changes.
        </p>
      </div>
    </Dialog>
  );
}

/** Destination chooser: copy the template to the dashboard or a course. */
export function UseTemplateDialog({
  template,
  courses,
  busy,
  error,
  onConfirm,
  onClose,
}: {
  template: Template;
  courses: Course[];
  busy: boolean;
  error: ApiError | Error | null;
  onConfirm: (destination: CopyDestination) => void;
  onClose: () => void;
}) {
  const [destination, setDestination] = useState<"dashboard" | "course">(
    "dashboard",
  );
  const [courseId, setCourseId] = useState("");
  const [localError, setLocalError] = useState<string | null>(null);

  const apiError = error instanceof ApiError ? error : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setLocalError(null);

    if (destination === "course" && courseId === "") {
      setLocalError("Choose a course for this copy.");
      return;
    }

    onConfirm(
      destination === "dashboard"
        ? { destination: "dashboard" }
        : { destination: "course", course_id: courseId },
    );
  };

  return (
    <Dialog
      open
      onClose={busy ? () => undefined : onClose}
      title="Use this template"
      description={`An independent editable copy of "${template.title}" will be added to your library.`}
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button
            type="submit"
            form="use-template-form"
            isLoading={busy}
            loadingLabel="Copying"
          >
            Create copy
          </Button>
        </>
      }
    >
      <form id="use-template-form" onSubmit={submit} className="space-y-4">
        {error && !apiError ? (
          <Alert variant="error" title="The copy could not be created">
            {error.message}
          </Alert>
        ) : null}

        <FormField label="Where should it go?">
          {(control) => (
            <Select
              {...control}
              value={destination}
              onChange={(event) =>
                setDestination(event.target.value as "dashboard" | "course")
              }
            >
              <option value="dashboard">My dashboard</option>
              <option value="course">A course</option>
            </Select>
          )}
        </FormField>

        {destination === "course" ? (
          <FormField
            label="Course"
            required
            error={localError ?? apiError?.fieldError("course_id")}
          >
            {(control) => (
              <Select
                {...control}
                value={courseId}
                onChange={(event) => setCourseId(event.target.value)}
              >
                <option value="">Choose a course</option>
                {courses.map((course) => (
                  <option key={course.id} value={course.id}>
                    {course.code ? `${course.code} · ` : ""}
                    {course.title}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        ) : null}

        <p className="text-caption text-text-muted">
          If an identical copy already sits in that destination, we open it
          instead of duplicating.
        </p>
      </form>
    </Dialog>
  );
}

/** Edit one template copy's title and body with optimistic concurrency. */
export function CopyEditorDialog({
  copy,
  busy,
  error,
  onSave,
  onClose,
}: {
  copy: TemplateCopy;
  busy: boolean;
  error: ApiError | Error | null;
  onSave: (input: { title: string; body: string }) => void;
  onClose: () => void;
}) {
  const [title, setTitle] = useState(copy.title);
  const [body, setBody] = useState(copy.body);

  const apiError = error instanceof ApiError ? error : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSave({ title: title.trim(), body });
  };

  return (
    <Dialog
      open
      onClose={busy ? () => undefined : onClose}
      title="Edit your copy"
      description={
        copy.source.template_title
          ? `From "${copy.source.template_title}"${
              copy.source.version_number
                ? ` (v${copy.source.version_number})`
                : ""
            }`
          : "Independent editable copy"
      }
      size="lg"
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button
            type="submit"
            form="copy-editor-form"
            isLoading={busy}
            loadingLabel="Saving"
            disabled={title.trim() === "" || body.trim() === ""}
          >
            Save changes
          </Button>
        </>
      }
    >
      <form id="copy-editor-form" onSubmit={submit} className="space-y-4">
        {error && !apiError ? (
          <Alert variant="error" title="Something went wrong">
            {error.message}
          </Alert>
        ) : null}
        {apiError && apiError.status === 409 ? (
          <Alert variant="error" title="This copy changed elsewhere">
            Close and reopen it to load the latest version, then edit again.
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
        <FormField label="Body" required error={apiError?.fieldError("body")}>
          {(control) => (
            <Textarea
              {...control}
              value={body}
              maxLength={20000}
              onChange={(event) => setBody(event.target.value)}
              className="min-h-64 font-mono text-caption"
            />
          )}
        </FormField>
        <p className="text-caption text-text-muted">
          The source template and its version never change. This is your own
          copy.
        </p>
      </form>
    </Dialog>
  );
}
