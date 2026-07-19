"use client";

import { Alert, Button, ErrorState } from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarPlus, Timer } from "lucide-react";
import Image from "next/image";
import { useEffect, useMemo, useState } from "react";

import { listCourses } from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import {
  createFocusSession,
  createTask,
  deleteFocusSession,
  deleteTask,
  archiveTask,
  getAgenda,
  getTask,
  getWeekly,
  listTasks,
  updateFocusSession,
  updateTask,
  updateTaskStatus,
  type FocusSession,
  type FocusSessionWriteInput,
  type Task,
  type TaskStatus,
  type TaskWriteInput,
} from "@/lib/api/planner";
import { courseKeys, plannerKeys } from "@/lib/query-keys";
import { browserTimezone } from "@/components/dashboard/format";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { AgendaPanel } from "./agenda-panel";
import { WeeklyBoard } from "./weekly-board";
import { UpcomingDeadlineCard, FocusSessionCard } from "./rail-cards";
import { SessionDialog } from "./session-dialog";
import { TaskDialog } from "./task-dialog";
import { localDateOf, mondayOf } from "./time";

type TaskDialogState = { task: Task | null } | null;
type SessionDialogState = { session: FocusSession | null } | null;
type ConfirmState =
  | { kind: "task-delete"; task: Task }
  | { kind: "task-archive"; task: Task }
  | { kind: "session-delete"; session: FocusSession }
  | null;

export function PlannerView() {
  const queryClient = useQueryClient();
  const [timezone] = useState(browserTimezone);
  const [now, setNow] = useState(() => new Date());
  const today = localDateOf(now, timezone);
  const currentWeekStart = mondayOf(today);
  const [weekStart, setWeekStart] = useState(currentWeekStart);
  const [agendaDate, setAgendaDate] = useState(today);

  const [taskDialog, setTaskDialog] = useState<TaskDialogState>(null);
  const [sessionDialog, setSessionDialog] = useState<SessionDialogState>(null);
  const [confirm, setConfirm] = useState<ConfirmState>(null);
  const [notice, setNotice] = useState<string | null>(null);

  /* The "running now" highlight stays truthful across long sessions. */
  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 60_000);

    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    if (notice === null) {
      return;
    }

    const timer = window.setTimeout(() => setNotice(null), 8_000);

    return () => window.clearTimeout(timer);
  }, [notice]);

  const weeklyQuery = useQuery({
    queryKey: plannerKeys.weekly(timezone, weekStart),
    queryFn: () => getWeekly(timezone, weekStart),
  });
  const agendaQuery = useQuery({
    queryKey: plannerKeys.agenda(timezone, agendaDate),
    queryFn: () => getAgenda(timezone, agendaDate),
  });
  const coursesQuery = useQuery({
    queryKey: courseKeys.list(),
    queryFn: () => listCourses({ status: "active", perPage: 50 }),
    staleTime: 5 * 60_000,
  });
  const openTasksQuery = useQuery({
    queryKey: ["planner", "task-picker"],
    queryFn: () => listTasks({ status: "all", perPage: 50 }),
    staleTime: 60_000,
  });

  const invalidatePlanner = () => {
    void queryClient.invalidateQueries({ queryKey: plannerKeys.all });
  };

  const refreshDialogTask = async (taskId: string) => {
    try {
      const fresh = await getTask(taskId);

      setTaskDialog((current) =>
        current && current.task?.id === taskId ? { task: fresh } : current,
      );
    } catch {
      /* The conflict alert already tells the user to review. */
    }
  };

  const createTaskMutation = useMutation({
    mutationFn: (input: TaskWriteInput) => createTask(input),
    onSuccess: () => {
      invalidatePlanner();
      setTaskDialog(null);
    },
  });

  const updateTaskMutation = useMutation({
    mutationFn: async ({
      task,
      input,
    }: {
      task: Task;
      input: TaskWriteInput & { status: TaskStatus };
    }) => {
      const { status, ...fields } = input;
      const updated = await updateTask(task.id, {
        ...fields,
        expected_version: task.version,
      });

      if (status !== task.status) {
        await updateTaskStatus(task.id, updated.version, status);
      }
    },
    onSuccess: () => {
      invalidatePlanner();
      setTaskDialog(null);
    },
    onError: (error, variables) => {
      if (error instanceof ApiError && error.status === 409) {
        void refreshDialogTask(variables.task.id);
      }
    },
  });

  const toggleTaskMutation = useMutation({
    mutationFn: ({ task }: { task: Task }) =>
      updateTaskStatus(
        task.id,
        task.version,
        task.status === "completed" ? "pending" : "completed",
      ),
    onSuccess: invalidatePlanner,
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) {
        invalidatePlanner();
        setNotice(
          "That task changed somewhere else, so the plan was refreshed. Try again.",
        );
      } else {
        setNotice("The task could not be updated. Please try again.");
      }
    },
  });

  const removeTaskMutation = useMutation({
    mutationFn: ({
      task,
      action,
    }: {
      task: Task;
      action: "delete" | "archive";
    }) =>
      action === "delete"
        ? deleteTask(task.id, task.version)
        : archiveTask(task.id, task.version).then(() => undefined),
    onSuccess: () => {
      invalidatePlanner();
      setConfirm(null);
      setTaskDialog(null);
    },
  });

  const createSessionMutation = useMutation({
    mutationFn: (input: FocusSessionWriteInput) => createFocusSession(input),
    onSuccess: () => {
      invalidatePlanner();
      setSessionDialog(null);
    },
  });

  const updateSessionMutation = useMutation({
    mutationFn: ({
      session,
      input,
    }: {
      session: FocusSession;
      input: FocusSessionWriteInput;
    }) =>
      updateFocusSession(session.id, {
        ...input,
        expected_version: session.version,
      }),
    onSuccess: () => {
      invalidatePlanner();
      setSessionDialog(null);
    },
  });

  const removeSessionMutation = useMutation({
    mutationFn: ({ session }: { session: FocusSession }) =>
      deleteFocusSession(session.id, session.version),
    onSuccess: () => {
      invalidatePlanner();
      setConfirm(null);
      setSessionDialog(null);
    },
  });

  const courses = coursesQuery.data?.data ?? [];
  const pickerTasks = useMemo(
    () =>
      (openTasksQuery.data?.data ?? []).filter(
        (task) => task.archive_status === "active",
      ),
    [openTasksQuery.data],
  );

  const openCreateTask = () => {
    createTaskMutation.reset();
    updateTaskMutation.reset();
    setTaskDialog({ task: null });
  };
  const openEditTask = (task: Task) => {
    createTaskMutation.reset();
    updateTaskMutation.reset();
    setTaskDialog({ task });
  };
  const openCreateSession = () => {
    createSessionMutation.reset();
    updateSessionMutation.reset();
    setSessionDialog({ session: null });
  };
  const openEditSession = (session: FocusSession) => {
    createSessionMutation.reset();
    updateSessionMutation.reset();
    setSessionDialog({ session });
  };

  const windowsFailed = weeklyQuery.isError && agendaQuery.isError;

  return (
    <div className="space-y-4">
      <header className="relative min-h-44 overflow-hidden rounded-xl border border-border-default lg:min-h-52">
        <Image
          src="/marketing/notebook-pens.jpg"
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
          <h1 className="text-h2 text-text-primary">Planner</h1>
          <p className="text-body-lg text-text-secondary">
            Plan your deadlines and focus time so you can stay ahead with
            confidence.
          </p>
          <div className="flex flex-wrap gap-2">
            <Button size="md" glow onClick={openCreateTask}>
              <CalendarPlus aria-hidden="true" className="size-4" />
              Add task
            </Button>
            <Button variant="secondary" size="md" onClick={openCreateSession}>
              <Timer aria-hidden="true" className="size-4" />
              Log focus session
            </Button>
          </div>
        </div>
      </header>

      {notice ? (
        <Alert variant="info" title="Plan refreshed">
          {notice}
        </Alert>
      ) : null}

      {windowsFailed ? (
        <ErrorState
          title="The planner could not load"
          description="Your plan is safe. This is a loading problem, not a data problem."
          onRetry={() => {
            void weeklyQuery.refetch();
            void agendaQuery.refetch();
          }}
        />
      ) : (
        <div className="flex flex-col gap-4 xl:grid xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
          <div className="order-2 xl:order-0">
            {weeklyQuery.isError ? (
              <ErrorState
                title="The weekly schedule could not load"
                onRetry={() => void weeklyQuery.refetch()}
              />
            ) : (
              <WeeklyBoard
                weekly={weeklyQuery.data}
                loading={weeklyQuery.isPending}
                timezone={timezone}
                today={today}
                weekStart={weekStart}
                onWeekChange={setWeekStart}
                currentWeekStart={currentWeekStart}
                onEditTask={openEditTask}
                onEditSession={openEditSession}
                onToggleTask={(task) => toggleTaskMutation.mutate({ task })}
                busyTaskId={
                  toggleTaskMutation.isPending
                    ? (toggleTaskMutation.variables?.task.id ?? null)
                    : null
                }
              />
            )}
          </div>

          <div className="order-1 space-y-4 xl:order-0">
            {agendaQuery.isError ? (
              <ErrorState
                title="The agenda could not load"
                onRetry={() => void agendaQuery.refetch()}
              />
            ) : (
              <AgendaPanel
                agenda={agendaQuery.data}
                loading={agendaQuery.isPending}
                timezone={timezone}
                date={agendaDate}
                today={today}
                onDateChange={setAgendaDate}
                onToggleTask={(task) => toggleTaskMutation.mutate({ task })}
                onEditTask={openEditTask}
                onEditSession={openEditSession}
                busyTaskId={
                  toggleTaskMutation.isPending
                    ? (toggleTaskMutation.variables?.task.id ?? null)
                    : null
                }
              />
            )}
            <UpcomingDeadlineCard
              tasks={weeklyQuery.data?.data.tasks ?? []}
              timezone={timezone}
              now={now}
              onEditTask={openEditTask}
            />
            <FocusSessionCard
              sessions={
                agendaDate === today
                  ? (agendaQuery.data?.data.focus_sessions ?? [])
                  : []
              }
              timezone={timezone}
              now={now}
              onEditSession={openEditSession}
              onLogSession={openCreateSession}
            />
          </div>
        </div>
      )}

      {taskDialog ? (
        <TaskDialog
          key={taskDialog.task?.id ?? "create"}
          open
          task={taskDialog.task}
          courses={courses}
          timezone={timezone}
          busy={createTaskMutation.isPending || updateTaskMutation.isPending}
          error={createTaskMutation.error ?? updateTaskMutation.error}
          onCreate={(input) => createTaskMutation.mutate(input)}
          onUpdate={(task, input) => updateTaskMutation.mutate({ task, input })}
          onRequestDelete={(task) => setConfirm({ kind: "task-delete", task })}
          onRequestArchive={(task) =>
            setConfirm({ kind: "task-archive", task })
          }
          onClose={() => setTaskDialog(null)}
        />
      ) : null}

      {sessionDialog ? (
        <SessionDialog
          key={sessionDialog.session?.id ?? "create"}
          open
          session={sessionDialog.session}
          defaultDate={agendaDate}
          tasks={pickerTasks}
          courses={courses}
          timezone={timezone}
          busy={
            createSessionMutation.isPending || updateSessionMutation.isPending
          }
          error={createSessionMutation.error ?? updateSessionMutation.error}
          onCreate={(input) => createSessionMutation.mutate(input)}
          onUpdate={(session, input) =>
            updateSessionMutation.mutate({ session, input })
          }
          onRequestDelete={(session) =>
            setConfirm({ kind: "session-delete", session })
          }
          onClose={() => setSessionDialog(null)}
        />
      ) : null}

      {confirm ? (
        <ConfirmDialog
          open
          title={
            confirm.kind === "task-delete"
              ? "Delete this task?"
              : confirm.kind === "task-archive"
                ? "Archive this task?"
                : "Delete this focus session?"
          }
          description={
            confirm.kind === "task-delete"
              ? `"${confirm.task.title}" will be permanently removed. Tasks with logged focus sessions cannot be deleted.`
              : confirm.kind === "task-archive"
                ? `"${confirm.task.title}" moves out of your plan but stays recoverable.`
                : "The planned focus time will be permanently removed."
          }
          confirmLabel={
            confirm.kind === "task-archive" ? "Archive task" : "Delete"
          }
          busy={removeTaskMutation.isPending || removeSessionMutation.isPending}
          error={
            confirm.kind === "task-delete" &&
            removeTaskMutation.error instanceof ApiError &&
            removeTaskMutation.error.status === 409
              ? "This task has focus sessions attached (or changed elsewhere). Archive it instead."
              : (removeTaskMutation.error?.message ??
                removeSessionMutation.error?.message ??
                null)
          }
          onConfirm={() => {
            if (confirm.kind === "session-delete") {
              removeSessionMutation.mutate({ session: confirm.session });
            } else {
              removeTaskMutation.mutate({
                task: confirm.task,
                action: confirm.kind === "task-delete" ? "delete" : "archive",
              });
            }
          }}
          onCancel={() => {
            removeTaskMutation.reset();
            removeSessionMutation.reset();
            setConfirm(null);
          }}
        />
      ) : null}
    </div>
  );
}
