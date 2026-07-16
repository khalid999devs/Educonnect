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
  Input,
  Label,
  Select,
} from "@educonnect/ui";
import {
  Brain,
  CalendarClock,
  CircleCheck,
  Clock,
  FileText,
  FolderOpen,
  LayoutTemplate,
  Lock,
  Plus,
  Search,
  Target,
  UploadCloud,
  Users,
} from "lucide-react";
import { useState } from "react";

import { demoProgress, useDemo } from "./demo-app";
import {
  DAY_LABELS,
  DEMO_COURSES,
  DEMO_TEMPLATES,
  FOCUS_SESSION,
  HOBBY_RHYTHM,
} from "./demo-data";
import { PageCover } from "./page-cover";
import { WeeklyChart } from "./weekly-chart";

const ORIGIN_LABEL = {
  starter: "Starter",
  intake: "From intake",
  template: "From template",
  manual: "Added here",
} as const;

const COURSE_CHIP: Record<string, string> = {
  "CS-201": "border-brand-primary/40 bg-brand-primary/15 text-brand-primary",
  "RM-110":
    "border-status-research/40 bg-status-research/15 text-status-research",
};

const COURSE_DOT: Record<string, string> = {
  "CS-201": "bg-brand-primary",
  "RM-110": "bg-status-research",
};

function PrivacyFootnote({ text }: { text: string }) {
  return (
    <p className="flex items-center justify-center gap-1.5 text-caption text-text-muted">
      <Lock aria-hidden="true" className="size-3" />
      {text}
    </p>
  );
}

export function PlannerView() {
  const { state, dispatch } = useDemo();
  const [formOpen, setFormOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [courseCode, setCourseCode] = useState("CS-201");
  const [dayIndex, setDayIndex] = useState(2);

  const tasks = [...state.tasks].sort((a, b) => a.dayIndex - b.dayIndex);
  const open = tasks.filter((t) => !t.completed);
  const nearestDeadline = open[0];

  const submitTask = () => {
    dispatch({ type: "addTask", title, courseCode, dayIndex });
    setTitle("");
    setFormOpen(false);
  };

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/minimal-desk.jpg"
        eyebrow="Planner"
        title="Plan the week with confidence"
        subtitle="Classes, deadlines, and focus time in one honest weekly view."
        action={
          <Button
            size="sm"
            aria-expanded={formOpen}
            onClick={() => setFormOpen((v) => !v)}
          >
            <Plus aria-hidden="true" className="size-4" />
            Add task
          </Button>
        }
      />

      {formOpen ? (
        <Card>
          <CardContent className="grid gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
            <div className="space-y-1.5">
              <Label htmlFor="demo-task-title">Task title</Label>
              <Input
                id="demo-task-title"
                value={title}
                placeholder="e.g. Revise graph traversal"
                onChange={(event) => setTitle(event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="demo-task-course">Course</Label>
              <Select
                id="demo-task-course"
                value={courseCode}
                onChange={(event) => setCourseCode(event.target.value)}
                className="sm:w-44"
              >
                {DEMO_COURSES.map((course) => (
                  <option key={course.id} value={course.code}>
                    {course.code} {course.name}
                  </option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="demo-task-day">Day</Label>
              <Select
                id="demo-task-day"
                value={String(dayIndex)}
                onChange={(event) => setDayIndex(Number(event.target.value))}
                className="sm:w-32"
              >
                {DAY_LABELS.map((day, index) => (
                  <option key={day} value={index}>
                    {day}
                  </option>
                ))}
              </Select>
            </div>
            <Button onClick={submitTask} disabled={title.trim().length === 0}>
              Add
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div className="space-y-4">
          <Card>
            <CardHeader className="mb-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <CardTitle as="h3">Weekly schedule</CardTitle>
                <p className="text-caption tabular-nums text-text-muted">
                  Sep 21 – Sep 27 · sample week
                </p>
              </div>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-7 gap-1.5">
                {DAY_LABELS.map((day, index) => {
                  const dayTasks = tasks.filter((t) => t.dayIndex === index);

                  return (
                    <div
                      key={day}
                      className="min-h-36 rounded-md border border-border-subtle bg-bg-canvas p-1.5"
                    >
                      <p className="text-center text-caption font-medium text-text-muted">
                        {day}
                        <span className="mt-0.5 block tabular-nums text-text-secondary">
                          {21 + index}
                        </span>
                      </p>
                      <div className="mt-1.5 space-y-1.5">
                        {dayTasks.map((task) => (
                          <button
                            key={task.id}
                            type="button"
                            title={`${task.title} — tap to toggle`}
                            onClick={() =>
                              dispatch({ type: "toggleTask", id: task.id })
                            }
                            className={cn(
                              "w-full rounded-sm border px-1.5 py-1 text-left text-caption leading-tight transition-opacity",
                              "focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-focus",
                              COURSE_CHIP[task.courseCode] ??
                                "border-border-strong bg-bg-interactive text-text-secondary",
                              task.completed && "line-through opacity-45",
                            )}
                          >
                            {task.title}
                          </button>
                        ))}
                        {index === 3 ? (
                          <span className="block rounded-sm border border-status-success/40 bg-status-success/15 px-1.5 py-1 text-caption leading-tight text-status-success">
                            {FOCUS_SESSION.label} · {FOCUS_SESSION.minutes}m
                            {state.focus === "done" ? " ✓" : ""}
                          </span>
                        ) : null}
                      </div>
                    </div>
                  );
                })}
              </div>
              <div className="mt-3 flex flex-wrap items-center gap-4">
                {DEMO_COURSES.map((course) => (
                  <span
                    key={course.id}
                    className="flex items-center gap-1.5 text-caption text-text-secondary"
                  >
                    <span
                      aria-hidden="true"
                      className={cn(
                        "size-2 rounded-full",
                        COURSE_DOT[course.code],
                      )}
                    />
                    {course.code} {course.name}
                  </span>
                ))}
                <span className="flex items-center gap-1.5 text-caption text-text-secondary">
                  <span
                    aria-hidden="true"
                    className="size-2 rounded-full bg-status-success"
                  />
                  Focus session
                </span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="mb-3">
              <CardTitle as="h3">Hobby calendar</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-7 gap-1.5">
                {HOBBY_RHYTHM.map((entry) => (
                  <div
                    key={entry.day}
                    className={cn(
                      "rounded-md border px-1.5 py-2 text-center",
                      entry.hobby
                        ? "border-status-research/40 bg-status-research/10"
                        : "border-border-subtle",
                    )}
                  >
                    <p className="text-caption text-text-muted">{entry.day}</p>
                    <p
                      className={cn(
                        "mt-0.5 truncate text-caption font-medium",
                        entry.hobby
                          ? "text-status-research"
                          : "text-text-muted",
                      )}
                    >
                      {entry.hobby ?? "—"}
                    </p>
                  </div>
                ))}
              </div>
              <p className="mt-2 text-caption text-text-muted">
                Optional and private in the product — never part of progress
                math.
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader className="mb-3">
              <CardTitle as="h3">Today's agenda</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2.5">
              {open.slice(0, 3).map((task) => (
                <label
                  key={task.id}
                  className="flex cursor-pointer items-start gap-2.5 rounded-md border border-border-subtle px-3 py-2.5"
                >
                  <input
                    type="checkbox"
                    checked={false}
                    onChange={() =>
                      dispatch({ type: "toggleTask", id: task.id })
                    }
                    className="mt-0.5 size-4 accent-brand-primary"
                    aria-label={`Mark "${task.title}" as done`}
                  />
                  <span className="min-w-0">
                    <span className="block truncate text-body text-text-primary">
                      {task.title}
                    </span>
                    <span className="block text-caption tabular-nums text-text-muted">
                      {task.courseCode} · due {task.due}
                    </span>
                  </span>
                </label>
              ))}
              {open.length === 0 ? (
                <p className="text-body text-text-secondary">
                  Everything is done — a real empty state.
                </p>
              ) : null}
            </CardContent>
          </Card>

          <Card className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="text-caption text-text-muted">Upcoming deadline</p>
              {nearestDeadline ? (
                <>
                  <p className="mt-1 truncate text-body font-semibold text-text-primary">
                    {nearestDeadline.title}
                  </p>
                  {nearestDeadline.dueNote ? (
                    <p className="text-caption font-semibold text-status-deadline">
                      {nearestDeadline.dueNote}
                    </p>
                  ) : null}
                  <p className="text-caption tabular-nums text-text-muted">
                    Due {nearestDeadline.due} · {nearestDeadline.courseCode}
                  </p>
                </>
              ) : (
                <p className="mt-1 text-body text-text-secondary">
                  No open deadlines.
                </p>
              )}
            </div>
            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-status-deadline/12">
              <CalendarClock
                aria-hidden="true"
                className="size-5 text-status-deadline"
              />
            </span>
          </Card>

          <Card className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="text-caption text-text-muted">Focus session</p>
              <p className="mt-1 text-body font-semibold text-text-primary">
                {FOCUS_SESSION.label} · {FOCUS_SESSION.minutes} min
              </p>
              <p className="text-caption text-text-secondary">
                {FOCUS_SESSION.topic}
              </p>
              {state.focus === "ready" ? (
                <Button
                  variant="secondary"
                  size="sm"
                  className="mt-2"
                  onClick={() => dispatch({ type: "startFocus" })}
                >
                  Start session
                </Button>
              ) : state.focus === "running" ? (
                <Button
                  size="sm"
                  className="mt-2"
                  onClick={() => dispatch({ type: "completeFocus" })}
                >
                  Complete session
                </Button>
              ) : (
                <p className="mt-2 text-caption font-medium text-status-success">
                  Completed — {FOCUS_SESSION.minutes} real minutes counted.
                </p>
              )}
            </div>
            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-status-success/12">
              <Target
                aria-hidden="true"
                className="size-5 text-status-success"
              />
            </span>
          </Card>

          <p className="text-caption text-text-muted">
            Origins are always visible:{" "}
            {Object.values(ORIGIN_LABEL).join(" · ")}.
          </p>
        </div>
      </div>
    </div>
  );
}

export function ResourcesView() {
  const { state, dispatch } = useDemo();

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/shelf-books.jpg"
        eyebrow="Resources"
        title="One private library"
        subtitle="Notes, files, and links organized by course — captured through Smart Intake, reviewed by you."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h3">Add material</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="rounded-lg border-2 border-dashed border-border-strong px-5 py-8 text-center">
              <span className="mx-auto flex size-12 items-center justify-center rounded-full bg-bg-interactive">
                <UploadCloud
                  aria-hidden="true"
                  className="size-5 text-brand-primary"
                />
              </span>
              <p className="mt-2 text-body font-medium text-text-primary">
                Materials arrive from Smart Intake in this demo
              </p>
              <p className="text-caption text-text-muted">
                Capture a sample document and confirm the suggestion to file it
                here.
              </p>
              <Button
                variant="secondary"
                size="sm"
                className="mt-3"
                onClick={() => dispatch({ type: "navigate", view: "intake" })}
              >
                Capture a sample
              </Button>
            </div>
            <PrivacyFootnote text="Private by default — the demo stores nothing at all." />
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center justify-between gap-2">
              <CardTitle as="h3">Organization preview</CardTitle>
              <Badge variant="ai">Reviewed by you</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {state.resources.length === 0 ? (
              <p className="text-body text-text-secondary">
                Nothing here yet — honest empty state. Confirmed captures show
                up with their course and type.
              </p>
            ) : (
              state.resources.map((resource) => (
                <div
                  key={resource.id}
                  className="flex items-center gap-3 rounded-md border border-border-subtle px-3 py-2.5"
                >
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-info/12 text-status-info">
                    <FileText aria-hidden="true" className="size-4" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-body font-medium text-text-primary">
                      {resource.title}
                    </p>
                    <p className="truncate text-caption text-text-muted">
                      {resource.meta}
                    </p>
                  </div>
                  <Badge variant="info">{resource.courseCode}</Badge>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="mb-3">
          <CardTitle as="h3">Recent materials</CardTitle>
        </CardHeader>
        <CardContent>
          {state.resources.length === 0 ? (
            <EmptyState
              icon={FolderOpen}
              title="No materials yet"
              description="Confirm a Smart Intake capture and it appears here with its course, type, and date."
              className="border-none bg-transparent py-8"
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-border-subtle text-caption text-text-muted">
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Course</th>
                    <th className="py-2 pr-4 font-medium">Type</th>
                    <th className="py-2 font-medium">Added</th>
                  </tr>
                </thead>
                <tbody>
                  {state.resources.map((resource) => (
                    <tr
                      key={resource.id}
                      className="border-b border-border-subtle last:border-none"
                    >
                      <td className="max-w-64 truncate py-2.5 pr-4 text-body text-text-primary">
                        {resource.title}
                      </td>
                      <td className="py-2.5 pr-4">
                        <Badge variant="neutral">{resource.courseCode}</Badge>
                      </td>
                      <td className="py-2.5 pr-4 text-body text-text-secondary">
                        Document
                      </td>
                      <td className="py-2.5 text-caption tabular-nums text-text-muted">
                        Today
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="grid gap-4 sm:grid-cols-3">
        {DEMO_COURSES.map((course) => {
          const count = state.resources.filter(
            (r) => r.courseCode === course.code,
          ).length;

          return (
            <Card key={course.id} className="flex items-center gap-3">
              <span className="flex size-11 shrink-0 items-center justify-center rounded-md bg-status-warning/12">
                <FolderOpen
                  aria-hidden="true"
                  className="size-5 text-status-warning"
                />
              </span>
              <span>
                <span className="block text-body font-semibold text-text-primary">
                  {course.code} {course.name}
                </span>
                <span className="block text-caption tabular-nums text-text-muted">
                  {count} {count === 1 ? "item" : "items"}
                </span>
              </span>
            </Card>
          );
        })}
        <span
          aria-disabled="true"
          className="flex min-h-20 items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border-strong text-body text-text-muted"
        >
          <Plus aria-hidden="true" className="size-4" />
          New collection · full product
        </span>
      </div>
    </div>
  );
}

export function BrainView() {
  const { state } = useDemo();
  const [query, setQuery] = useState("");
  const needle = query.trim().toLowerCase();
  const notes = state.notes.filter(
    (note) =>
      needle.length === 0 ||
      `${note.title} ${note.meta} ${note.courseCode}`
        .toLowerCase()
        .includes(needle),
  );

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/library-curve.jpg"
        eyebrow="Second Brain"
        title="Keep what you learn"
        subtitle="Every note stays connected to its source — search it all, instantly."
      />

      <div className="relative max-w-md">
        <Search
          aria-hidden="true"
          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
        />
        <Input
          type="search"
          aria-label="Search your notes"
          placeholder="Search notes, topics, courses…"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          className="pl-9"
        />
      </div>

      {notes.length === 0 ? (
        <p className="text-body text-text-secondary">
          Nothing matches "{query.trim()}" — real search over your demo notes.
        </p>
      ) : (
        <ul className="grid gap-3 md:grid-cols-2">
          {notes.map((note) => (
            <li
              key={note.id}
              className="rounded-lg border border-border-default bg-bg-surface p-4 transition-colors hover:border-border-strong"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="flex items-center gap-2 text-body font-semibold text-text-primary">
                  <Brain
                    aria-hidden="true"
                    className="size-4 text-status-research"
                  />
                  {note.title}
                </p>
                {note.date === "Today" ? (
                  <Badge variant="brand">New</Badge>
                ) : null}
              </div>
              <p className="mt-1.5 text-body text-text-secondary">
                {note.meta}
              </p>
              <p className="mt-2 flex items-center gap-2 text-caption tabular-nums text-text-muted">
                <Badge variant="research">{note.courseCode}</Badge>
                {note.date} · linked to its source
              </p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export function ProgressView() {
  const { state } = useDemo();
  const progress = demoProgress(state);
  const completedTasks = state.tasks.filter((t) => t.completed);
  const usedTemplates = DEMO_TEMPLATES.filter((t) =>
    state.usedTemplateIds.includes(t.id),
  );

  const milestones: Array<{
    id: string;
    icon: typeof CircleCheck;
    tint: string;
    title: string;
    meta: string;
  }> = [
    ...completedTasks.map((task) => ({
      id: `done-${task.id}`,
      icon: CircleCheck,
      tint: "bg-status-success/12 text-status-success",
      title: `Completed: ${task.title}`,
      meta: `${task.courseCode} · was due ${task.due}`,
    })),
    ...(state.resources.length > 0
      ? [
          {
            id: "resources",
            icon: FolderOpen,
            tint: "bg-status-info/12 text-status-info",
            title: `Filed ${state.resources.length} ${state.resources.length === 1 ? "source" : "sources"} under courses`,
            meta: "via a reviewed Smart Intake capture",
          },
        ]
      : []),
    ...state.notes
      .filter((note) => note.date === "Today")
      .map((note) => ({
        id: `note-${note.id}`,
        icon: Brain,
        tint: "bg-status-research/12 text-status-research",
        title: "Added a key-term note",
        meta: "linked to its source document",
      })),
    ...usedTemplates.map((template) => ({
      id: `tpl-${template.id}`,
      icon: LayoutTemplate,
      tint: "bg-status-ai/12 text-status-ai",
      title: `Created your copy: ${template.name}`,
      meta: "independent and editable",
    })),
    ...(state.focus === "done"
      ? [
          {
            id: "focus",
            icon: Target,
            tint: "bg-status-success/12 text-status-success",
            title: `Completed ${FOCUS_SESSION.label}`,
            meta: `${FOCUS_SESSION.minutes} focus minutes`,
          },
        ]
      : []),
  ];

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/minimal-desk.jpg"
        eyebrow="Progress"
        title="Real momentum only"
        subtitle="Every number below comes from what you actually did in this demo — no streaks, no percentiles."
      />

      <div className="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
        <Card>
          <CardHeader className="mb-3">
            <div className="flex items-center justify-between gap-2">
              <CardTitle as="h3">Progress summary</CardTitle>
              <Badge variant="neutral">This week</Badge>
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap items-center gap-6">
              <div
                role="img"
                aria-label={`${progress.percent} percent of this week's tasks completed`}
                className="relative flex size-32 shrink-0 items-center justify-center rounded-full"
                style={{
                  background: `conic-gradient(var(--brand-primary) ${progress.percent * 3.6}deg, var(--bg-interactive) 0deg)`,
                }}
              >
                <span className="flex size-25 flex-col items-center justify-center rounded-full bg-bg-surface text-center">
                  <span className="text-h2 tabular-nums text-text-primary">
                    {progress.percent}%
                  </span>
                  <span className="px-3 text-caption leading-tight text-text-muted">
                    weekly tasks
                  </span>
                </span>
              </div>
              <ul className="min-w-0 flex-1 space-y-2.5">
                {[
                  {
                    icon: CircleCheck,
                    tint: "text-status-success",
                    label: "Tasks completed",
                    value: `${progress.completed} / ${progress.total}`,
                  },
                  {
                    icon: Clock,
                    tint: "text-brand-primary",
                    label: "Focus minutes",
                    value: `${progress.focusMinutes}`,
                  },
                  {
                    icon: FolderOpen,
                    tint: "text-status-info",
                    label: "Sources filed",
                    value: `${state.resources.length}`,
                  },
                  {
                    icon: Brain,
                    tint: "text-status-research",
                    label: "Knowledge notes",
                    value: `${state.notes.length}`,
                  },
                ].map((row) => (
                  <li
                    key={row.label}
                    className="flex items-center gap-3 rounded-md border border-border-subtle px-3.5 py-2.5"
                  >
                    <row.icon
                      aria-hidden="true"
                      className={cn("size-4 shrink-0", row.tint)}
                    />
                    <span className="flex-1 text-body text-text-secondary">
                      {row.label}
                    </span>
                    <span className="text-body font-semibold tabular-nums text-text-primary">
                      {row.value}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="mb-3">
            <CardTitle as="h3">Milestones — real events</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {milestones.length === 0 ? (
              <p className="text-body text-text-secondary">
                Do things in the demo — complete a task, confirm a capture, use
                a template — and they appear here. Nothing is invented.
              </p>
            ) : (
              milestones.map((milestone) => (
                <div
                  key={milestone.id}
                  className="flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3"
                >
                  <span
                    className={cn(
                      "flex size-9 shrink-0 items-center justify-center rounded-full",
                      milestone.tint,
                    )}
                  >
                    <milestone.icon aria-hidden="true" className="size-4" />
                  </span>
                  <span className="min-w-0">
                    <span className="block truncate text-body font-medium text-text-primary">
                      {milestone.title}
                    </span>
                    <span className="block text-caption text-text-muted">
                      {milestone.meta}
                    </span>
                  </span>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="mb-3">
          <CardTitle as="h3">This week's activity</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <WeeklyChart weekly={progress.weekly} />
          <p className="text-body text-text-primary">
            You completed{" "}
            <span className="font-semibold tabular-nums">
              {progress.completed} of {progress.total}
            </span>{" "}
            tasks this week
            {progress.focusMinutes > 0 ? (
              <>
                {" "}
                and logged{" "}
                <span className="font-semibold tabular-nums">
                  {progress.focusMinutes}
                </span>{" "}
                focus minutes
              </>
            ) : null}
            .{" "}
            {progress.nextTask
              ? `Specific next action: ${progress.nextTask.title} — due ${progress.nextTask.due} (${progress.nextTask.courseCode}).`
              : "Everything is done — a real empty state, not a badge."}
          </p>
        </CardContent>
      </Card>
    </div>
  );
}

export function CommunityView() {
  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-h3 text-text-primary">Community</h2>
      </header>
      <EmptyState
        icon={Users}
        title="Not part of this demo"
        description="Curated communities and mentor help arrive later in the MVP. The demo doesn't fake posts, members, or mentors."
      />
      <p className="text-center text-caption text-text-muted">
        Feed-first, moderated, and honest — when it ships.
      </p>
    </div>
  );
}
