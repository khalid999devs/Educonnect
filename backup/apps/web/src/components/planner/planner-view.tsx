"use client";

import { Alert, Button, ErrorState } from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Archive,
  CalendarDays,
  CalendarPlus,
  ListChecks,
  Timer,
} from "lucide-react";
import { PageCover } from "@/components/shared/page-cover";
import { useEffect, useState } from "react";

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
  restoreTask,
  updateFocusSession,
  updateTask,
  updateTaskStatus,
  type FocusSession,
  type FocusSessionWriteInput,
  type Task,
  type TaskStatus,
  type TaskWriteInput,
} from "@/lib/api/planner";
import {
  ACTIVE_COURSE_LIST_PARAMS,
  fetchAllActiveCourses,
} from "@/lib/api/courses";
import { courseKeys, plannerKeys } from "@/lib/query-keys";
import { browserTimezone } from "@/components/dashboard/format";
import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { SectionTabs, type SectionTab } from "@/components/shared/section-tabs";
import { AgendaPanel } from "./agenda-panel";
import { WeeklyBoard } from "./weekly-board";
import { fetchTaskPickerOptions } from "./planner-fetches";
import { UpcomingDeadlineCard, FocusSessionCard } from "./rail-cards";
import { SessionDialog } from "./session-dialog";
import { TaskDialog } from "./task-dialog";
import { TaskList } from "./task-list";
import { localDateOf, mondayOf } from "./time";

type TaskDialogState = { task: Task | null } | null;
type SessionDialogState = { session: FocusSession | null } | null;
type ConfirmState =
  | { kind: "task-delete"; task: Task }
  | { kind: "task-archive"; task: Task }
  | { kind: "session-delete"; session: FocusSession }
  | null;

/** The planner is now the only task surface, so it carries the schedule, the
 * full task list (undated tasks included), and the archive. */
type PlannerTab = "schedule" | "tasks" | "archived";

const PLANNER_TABS: readonly SectionTab<PlannerTab>[] = [
  { value: "schedule", label: "Schedule", icon: CalendarDays },
  { value: "tasks", label: "All tasks", icon: ListChecks },
  { value: "archived", label: "Archived", icon: Archive },
];

export function PlannerView() {
  const queryClient = useQueryClient();
  const [timezone] = useState(browserTimezone);
  const [now, setNow] = useState(() => new Date());
  const today = localDateOf(now, timezone);
  const currentWeekStart = mondayOf(today);
  const [weekStart, setWeekStart] = useState(currentWeekStart);
  const [agendaDate, setAgendaDate] = useState(today);
  const [tab, setTab] = useState<PlannerTab>("schedule");

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
    enabled: tab === "schedule",
  });
  const agendaQuery = useQuery({
    queryKey: plannerKeys.agenda(timezone, agendaDate),
    queryFn: () => getAgenda(timezone, agendaDate),
    enabled: tab === "schedule",
  });
  /* Both pickers follow the cursor: `perPage: 50` with no cursor silently
     dropped every course and task past the first page. */
  const coursesQuery = useQuery({
    queryKey: courseKeys.list(ACTIVE_COURSE_LIST_PARAMS),
    queryFn: fetchAllActiveCourses,
    staleTime: 5 * 60_000,
  });
  const openTasksQuery = useQuery({
    queryKey: plannerKeys.taskPicker(),
    queryFn: fetchTaskPickerOptions,
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

  /* Restoring is not destructive, so it is a one-click inline action with no
     confirmation step. */
  const restoreTaskMutation = useMutation({
    mutationFn: ({ task }: { task: Task }) =>
      restoreTask(task.id, task.version),
    onSuccess: (task) => {
      invalidatePlanner();
      setNotice(`"${task.title}" is back in your plan.`);
    },
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) {
        invalidatePlanner();
        setNotice(
          "That task changed somewhere else, so the list was refreshed. Try again.",
        );
      } else {
        setNotice("The task could not be restored. Please try again.");
      }
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

  const courses = coursesQuery.data ?? [];
  /* The picker query already asks the server for active tasks only, so no
     client-side narrowing is left to drift. */
  const pickerTasks = openTasksQuery.data ?? [];

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
  const busyTaskId =
    toggleTaskMutation.isPending && toggleTaskMutation.variables
      ? toggleTaskMutation.variables.task.id
      : restoreTaskMutation.isPending && restoreTaskMutation.variables
        ? restoreTaskMutation.variables.task.id
        : null;

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/notebook-pens.jpg"
        headingLevel={1}
        tall
        priority
        title="Planner"
        subtitle="Plan your deadlines and focus time so you can stay ahead with confidence."
        action={
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
        }
      />

      <SectionTabs
        tabs={PLANNER_TABS}
        value={tab}
        onChange={setTab}
        label="Planner sections"
        panelId={(value) => `planner-panel-${value}`}
      />

      {notice ? (
        <Alert variant="info" title="Planner updated">
          {notice}
        </Alert>
      ) : null}

      {tab === "tasks" ? (
        <div
          id="planner-panel-tasks"
          role="tabpanel"
          aria-labelledby="section-tab-tasks"
        >
          <TaskList
            mode="active"
            courses={courses}
            timezone={timezone}
            now={now}
            busyTaskId={busyTaskId}
            onEditTask={openEditTask}
            onToggleTask={(task) => toggleTaskMutation.mutate({ task })}
            onArchiveTask={(task) => setConfirm({ kind: "task-archive", task })}
            onRestoreTask={(task) => restoreTaskMutation.mutate({ task })}
            onCreateTask={openCreateTask}
          />
        </div>
      ) : tab === "archived" ? (
        <div
          id="planner-panel-archived"
          role="tabpanel"
          aria-labelledby="section-tab-archived"
        >
          <TaskList
            mode="archived"
            courses={courses}
            timezone={timezone}
            now={now}
            busyTaskId={busyTaskId}
            onEditTask={openEditTask}
            onToggleTask={(task) => toggleTaskMutation.mutate({ task })}
            onArchiveTask={(task) => setConfirm({ kind: "task-archive", task })}
            onRestoreTask={(task) => restoreTaskMutation.mutate({ task })}
            onCreateTask={openCreateTask}
          />
        </div>
      ) : windowsFailed ? (
        <ErrorState
          title="The planner could not load"
          description="Your plan is safe. This is a loading problem, not a data problem."
          onRetry={() => {
            void weeklyQuery.refetch();
            void agendaQuery.refetch();
          }}
        />
      ) : (
        <div
          id="planner-panel-schedule"
          role="tabpanel"
          aria-labelledby="section-tab-schedule"
          className="flex flex-col gap-4 motion-safe:animate-fade-up xl:grid xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start"
        >
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
                busyTaskId={busyTaskId}
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
                busyTaskId={busyTaskId}
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
