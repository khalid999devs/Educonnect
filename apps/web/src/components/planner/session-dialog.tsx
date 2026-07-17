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
import type {
  FocusSession,
  FocusSessionWriteInput,
  Task,
} from "@/lib/api/planner";
import {
  instantFromLocalInput,
  localDateOf,
  localTimeInputValue,
  toApiInstant,
} from "./time";

type AttachChoice = "none" | "task" | "course";

export type SessionDialogProps = {
  open: boolean;
  session: FocusSession | null;
  /** Suggested local date for a new session (agenda date). */
  defaultDate: string;
  tasks: Task[];
  courses: Course[];
  timezone: string;
  busy: boolean;
  onCreate: (input: FocusSessionWriteInput) => void;
  onUpdate: (session: FocusSession, input: FocusSessionWriteInput) => void;
  onRequestDelete: (session: FocusSession) => void;
  onClose: () => void;
  error: ApiError | Error | null;
};

/** Create/edit form for a focus session. A session may attach to one owned
 * task, one owned course, or neither — never both (planner contract). */
export function SessionDialog({
  open,
  session,
  defaultDate,
  tasks,
  courses,
  timezone,
  busy,
  onCreate,
  onUpdate,
  onRequestDelete,
  onClose,
  error,
}: SessionDialogProps) {
  const editing = session !== null;
  const [attach, setAttach] = useState<AttachChoice>(
    session?.task ? "task" : session?.course ? "course" : "none",
  );
  const [taskId, setTaskId] = useState(session?.task?.id ?? "");
  const [courseId, setCourseId] = useState(session?.course?.id ?? "");
  const [date, setDate] = useState(
    session ? localDateOf(new Date(session.starts_at), timezone) : defaultDate,
  );
  const [startsTime, setStartsTime] = useState(
    session ? localTimeInputValue(session.starts_at, timezone) : "09:00",
  );
  const [endsTime, setEndsTime] = useState(
    session ? localTimeInputValue(session.ends_at, timezone) : "09:50",
  );
  const [note, setNote] = useState(session?.note ?? "");
  const [localError, setLocalError] = useState<string | null>(null);

  const apiError = error instanceof ApiError ? error : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setLocalError(null);

    const startsAt = instantFromLocalInput(date, startsTime);
    const endsAt = instantFromLocalInput(date, endsTime);

    if (endsAt.getTime() <= startsAt.getTime()) {
      setLocalError("The session must end after it starts.");
      return;
    }

    const input: FocusSessionWriteInput = {
      task_id: attach === "task" && taskId !== "" ? taskId : null,
      course_id: attach === "course" && courseId !== "" ? courseId : null,
      starts_at: toApiInstant(startsAt),
      ends_at: toApiInstant(endsAt),
      note: note.trim() === "" ? null : note.trim(),
    };

    if (editing) {
      onUpdate(session, input);
    } else {
      onCreate(input);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={busy ? () => undefined : onClose}
      title={editing ? "Edit focus session" : "Log focus session"}
      description="Planned, distraction-free time in your own timezone."
      footer={
        <>
          {editing ? (
            <Button
              variant="ghost"
              size="sm"
              disabled={busy}
              className="mr-auto text-status-error"
              onClick={() => onRequestDelete(session)}
            >
              Delete
            </Button>
          ) : null}
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button
            type="submit"
            form="session-dialog-form"
            isLoading={busy}
            loadingLabel="Saving"
          >
            {editing ? "Save changes" : "Log session"}
          </Button>
        </>
      }
    >
      <form id="session-dialog-form" onSubmit={submit} className="space-y-4">
        {error && !apiError ? (
          <Alert variant="error" title="Something went wrong">
            {error.message}
          </Alert>
        ) : null}
        {apiError && apiError.status === 409 ? (
          <Alert variant="error" title="This session changed elsewhere">
            The latest version was reloaded — please review and save again.
          </Alert>
        ) : null}
        {localError ? (
          <Alert variant="error" title="Check the times">
            {localError}
          </Alert>
        ) : null}

        <fieldset className="space-y-2">
          <legend className="text-body font-medium text-text-primary">
            Attach to
          </legend>
          <div className="flex flex-wrap gap-3">
            {(
              [
                ["none", "Nothing"],
                ["task", "A task"],
                ["course", "A course"],
              ] as const
            ).map(([value, label]) => (
              <label
                key={value}
                className="flex items-center gap-2 text-body text-text-secondary"
              >
                <input
                  type="radio"
                  name="session-attach"
                  value={value}
                  checked={attach === value}
                  onChange={() => setAttach(value)}
                  className="size-4 accent-brand-primary"
                />
                {label}
              </label>
            ))}
          </div>
        </fieldset>

        {attach === "task" ? (
          <FormField label="Task" error={apiError?.fieldError("task_id")}>
            {(control) => (
              <Select
                {...control}
                value={taskId}
                onChange={(event) => setTaskId(event.target.value)}
              >
                <option value="">Choose a task</option>
                {tasks.map((task) => (
                  <option key={task.id} value={task.id}>
                    {task.title}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        ) : null}

        {attach === "course" ? (
          <FormField label="Course" error={apiError?.fieldError("course_id")}>
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

        <div className="grid gap-4 sm:grid-cols-3">
          <FormField label="Date" error={apiError?.fieldError("starts_at")}>
            {(control) => (
              <Input
                {...control}
                type="date"
                value={date}
                onChange={(event) => setDate(event.target.value)}
              />
            )}
          </FormField>
          <FormField label="Starts">
            {(control) => (
              <Input
                {...control}
                type="time"
                value={startsTime}
                onChange={(event) => setStartsTime(event.target.value)}
              />
            )}
          </FormField>
          <FormField label="Ends" error={apiError?.fieldError("ends_at")}>
            {(control) => (
              <Input
                {...control}
                type="time"
                value={endsTime}
                onChange={(event) => setEndsTime(event.target.value)}
              />
            )}
          </FormField>
        </div>

        <FormField label="Note" error={apiError?.fieldError("note")}>
          {(control) => (
            <Textarea
              {...control}
              value={note}
              maxLength={2000}
              onChange={(event) => setNote(event.target.value)}
              placeholder="Optional — what will you focus on?"
            />
          )}
        </FormField>
      </form>
    </Dialog>
  );
}
