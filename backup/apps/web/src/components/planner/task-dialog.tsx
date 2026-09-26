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
import type { Task, TaskStatus, TaskWriteInput } from "@/lib/api/planner";
import {
  instantFromLocalInput,
  localDateOf,
  localTimeInputValue,
  toApiInstant,
} from "./time";

export type TaskDialogProps = {
  open: boolean;
  task: Task | null;
  courses: Course[];
  timezone: string;
  busy: boolean;
  onCreate: (input: TaskWriteInput) => void;
  onUpdate: (
    task: Task,
    input: TaskWriteInput & { status: TaskStatus },
  ) => void;
  onRequestDelete: (task: Task) => void;
  onRequestArchive: (task: Task) => void;
  onClose: () => void;
  error: ApiError | Error | null;
};

/** Create/edit form for one task; due date and time are entered in the
 * user's own timezone and sent as a second-precision UTC instant. */
export function TaskDialog({
  open,
  task,
  courses,
  timezone,
  busy,
  onCreate,
  onUpdate,
  onRequestDelete,
  onRequestArchive,
  onClose,
  error,
}: TaskDialogProps) {
  const [title, setTitle] = useState(task?.title ?? "");
  const [description, setDescription] = useState(task?.description ?? "");
  const [courseId, setCourseId] = useState(task?.course?.id ?? "");
  const [status, setStatus] = useState<TaskStatus>(task?.status ?? "pending");
  const [dueDate, setDueDate] = useState(
    task?.due_at ? localDateOf(new Date(task.due_at), timezone) : "",
  );
  const [dueTime, setDueTime] = useState(
    task?.due_at ? localTimeInputValue(task.due_at, timezone) : "",
  );
  const [localError, setLocalError] = useState<string | null>(null);

  const apiError = error instanceof ApiError ? error : null;
  const editing = task !== null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setLocalError(null);

    if (dueDate !== "" && dueTime === "") {
      setLocalError("Add a time for the due date, or clear the date.");
      return;
    }

    const input: TaskWriteInput = {
      title: title.trim(),
      description: description.trim() === "" ? null : description.trim(),
      course_id: courseId === "" ? null : courseId,
      due_at:
        dueDate === ""
          ? null
          : toApiInstant(instantFromLocalInput(dueDate, dueTime)),
    };

    if (editing) {
      onUpdate(task, { ...input, status });
    } else {
      onCreate(input);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={busy ? () => undefined : onClose}
      title={editing ? "Edit task" : "New task"}
      description={
        editing
          ? undefined
          : "Tasks with a due date appear in your agenda and weekly plan."
      }
      footer={
        <>
          {editing ? (
            <div className="mr-auto flex flex-wrap items-center gap-2">
              <Button
                variant="ghost"
                size="sm"
                disabled={busy}
                onClick={() => onRequestArchive(task)}
              >
                Archive
              </Button>
              <Button
                variant="ghost"
                size="sm"
                disabled={busy}
                className="text-status-error"
                onClick={() => onRequestDelete(task)}
              >
                Delete
              </Button>
            </div>
          ) : null}
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button
            type="submit"
            form="task-dialog-form"
            isLoading={busy}
            loadingLabel="Saving"
            disabled={title.trim() === ""}
          >
            {editing ? "Save changes" : "Create task"}
          </Button>
        </>
      }
    >
      <form id="task-dialog-form" onSubmit={submit} className="space-y-4">
        {error && !apiError ? (
          <Alert variant="error" title="Something went wrong">
            {error.message}
          </Alert>
        ) : null}
        {apiError && apiError.status === 409 ? (
          <Alert variant="error" title="This task changed elsewhere">
            The latest version was reloaded. Please review and save again.
          </Alert>
        ) : null}

        <FormField label="Title" required error={apiError?.fieldError("title")}>
          {(control) => (
            <Input
              {...control}
              value={title}
              maxLength={160}
              onChange={(event) => setTitle(event.target.value)}
              placeholder="e.g. Review AVL trees"
            />
          )}
        </FormField>

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

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            label="Due date"
            hint="Shown in your timezone"
            error={apiError?.fieldError("due_at")}
          >
            {(control) => (
              <Input
                {...control}
                type="date"
                value={dueDate}
                onChange={(event) => setDueDate(event.target.value)}
              />
            )}
          </FormField>
          <FormField label="Due time" error={localError ?? undefined}>
            {(control) => (
              <Input
                {...control}
                type="time"
                value={dueTime}
                onChange={(event) => setDueTime(event.target.value)}
              />
            )}
          </FormField>
        </div>

        {editing ? (
          <FormField label="Status" error={apiError?.fieldError("status")}>
            {(control) => (
              <Select
                {...control}
                value={status}
                onChange={(event) =>
                  setStatus(event.target.value as TaskStatus)
                }
              >
                <option value="pending">Pending</option>
                <option value="in_progress">In progress</option>
                <option value="completed">Completed</option>
              </Select>
            )}
          </FormField>
        ) : null}
      </form>
    </Dialog>
  );
}
