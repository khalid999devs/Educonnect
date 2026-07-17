"use client";

import {
  Badge,
  Button,
  buttonClasses,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  CopilotTrigger,
  EduConnectThemedLogo,
  ThemeToggle,
} from "@educonnect/ui";
import Link from "next/link";
import {
  Bell,
  Brain,
  CalendarDays,
  Inbox,
  LayoutDashboard,
  LayoutTemplate,
  Library,
  RotateCcw,
  TrendingUp,
  Users,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import {
  createContext,
  useContext,
  useReducer,
  useState,
  type Dispatch,
  type ReactNode,
} from "react";

import {
  ANALYSIS_STAGES,
  DEMO_DOCUMENTS,
  DUE_BY_DAY,
  DEMO_PERSONA,
  DEMO_TEMPLATES,
  FOCUS_SESSION,
  HOBBY_RHYTHM,
  INITIAL_TASKS,
  JOURNEY_CARD,
  SECOND_BRAIN_SEEDS,
  type DemoTaskSeed,
} from "./demo-data";
import { DemoSearch } from "./demo-search";
import { DashboardView } from "./views-dashboard";
import { IntakeView } from "./views-intake";
import { TemplatesView, ToolsView } from "./views-guidance";
import {
  BrainView,
  CommunityView,
  PlannerView,
  ProgressView,
  ResourcesView,
} from "./views-workspace";

export type DemoView =
  | "dashboard"
  | "intake"
  | "planner"
  | "resources"
  | "tools"
  | "templates"
  | "brain"
  | "community"
  | "progress";

export type DemoResource = {
  id: string;
  title: string;
  courseCode: string;
  meta: string;
};

export type DemoNote = {
  id: string;
  title: string;
  courseCode: string;
  meta: string;
  date: string;
};

export type IntakeStatus =
  "idle" | "selected" | "analyzing" | "ready" | "confirmed";

export type DemoState = {
  view: DemoView;
  tasks: DemoTaskSeed[];
  resources: DemoResource[];
  notes: DemoNote[];
  savedToolIds: string[];
  usedTemplateIds: string[];
  focus: "ready" | "running" | "done";
  intake: {
    docId: string | null;
    status: IntakeStatus;
    stage: number;
    accepted: string[];
  };
};

export type DemoAction =
  | { type: "navigate"; view: DemoView }
  | { type: "chooseDocument"; docId: string }
  | { type: "selectDocument"; docId: string }
  | { type: "startAnalysis" }
  | { type: "addTask"; title: string; courseCode: string; dayIndex: number }
  | { type: "advanceStage" }
  | { type: "toggleSuggestion"; id: string }
  | { type: "confirmIntake" }
  | { type: "captureAnother" }
  | { type: "toggleTask"; id: string }
  | { type: "toggleTool"; id: string }
  | { type: "useTemplate"; templateId: string; courseCode: string }
  | { type: "startFocus" }
  | { type: "completeFocus" }
  | { type: "reset" };

const INITIAL_STATE: DemoState = {
  view: "dashboard",
  tasks: INITIAL_TASKS,
  resources: [],
  notes: SECOND_BRAIN_SEEDS,
  savedToolIds: ["workflow"],
  usedTemplateIds: [],
  focus: "ready",
  intake: { docId: null, status: "idle", stage: 0, accepted: [] },
};

function demoReducer(state: DemoState, action: DemoAction): DemoState {
  switch (action.type) {
    case "navigate":
      return { ...state, view: action.view };

    case "chooseDocument": {
      const doc = DEMO_DOCUMENTS.find((d) => d.id === action.docId);

      if (!doc) {
        return state;
      }

      return {
        ...state,
        intake: {
          docId: doc.id,
          status: "analyzing",
          stage: 0,
          accepted: doc.suggestions.map((s) => s.id),
        },
      };
    }

    case "selectDocument": {
      const doc = DEMO_DOCUMENTS.find((d) => d.id === action.docId);

      if (!doc) {
        return state;
      }

      return {
        ...state,
        intake: {
          docId: doc.id,
          status: "selected",
          stage: 0,
          accepted: doc.suggestions.map((s) => s.id),
        },
      };
    }

    case "startAnalysis": {
      if (state.intake.status !== "selected") {
        return state;
      }

      return { ...state, intake: { ...state.intake, status: "analyzing" } };
    }

    case "addTask": {
      const title = action.title.trim();

      if (title.length === 0) {
        return state;
      }

      const manualCount = state.tasks.filter((t) =>
        t.id.startsWith("manual-"),
      ).length;

      return {
        ...state,
        tasks: [
          ...state.tasks,
          {
            id: `manual-${manualCount + 1}`,
            title,
            courseCode: action.courseCode,
            due: DUE_BY_DAY[action.dayIndex] ?? "Sun, Sep 27",
            dayIndex: action.dayIndex,
            completed: false,
            origin: "manual" as const,
          },
        ],
      };
    }

    case "advanceStage": {
      if (state.intake.status !== "analyzing") {
        return state;
      }

      if (state.intake.stage >= ANALYSIS_STAGES.length - 1) {
        return { ...state, intake: { ...state.intake, status: "ready" } };
      }

      return {
        ...state,
        intake: { ...state.intake, stage: state.intake.stage + 1 },
      };
    }

    case "toggleSuggestion": {
      const accepted = state.intake.accepted.includes(action.id)
        ? state.intake.accepted.filter((id) => id !== action.id)
        : [...state.intake.accepted, action.id];

      return { ...state, intake: { ...state.intake, accepted } };
    }

    case "confirmIntake": {
      const doc = DEMO_DOCUMENTS.find((d) => d.id === state.intake.docId);

      if (!doc || state.intake.status !== "ready") {
        return state;
      }

      const chosen = doc.suggestions.filter((s) =>
        state.intake.accepted.includes(s.id),
      );
      /* Structural idempotency, like the product: existing ids never
         duplicate on a repeated confirmation. */
      const taskIds = new Set(state.tasks.map((t) => t.id));
      const resourceIds = new Set(state.resources.map((r) => r.id));
      const noteIds = new Set(state.notes.map((n) => n.id));

      const newTasks = chosen
        .filter((s) => s.kind === "task" && s.task && !taskIds.has(s.id))
        .map((s) => ({
          id: s.id,
          title: s.task?.title ?? s.label,
          courseCode: s.task?.courseCode ?? "CS-201",
          due: s.task?.due ?? "",
          dayIndex: s.task?.dayIndex ?? 0,
          completed: false,
          origin: "intake" as const,
        }));
      const newResources = chosen
        .filter((s) => s.kind === "resource" && !resourceIds.has(s.id))
        .map((s) => ({
          id: s.id,
          title: doc.name,
          courseCode: s.detail.includes("RM-110") ? "RM-110" : "CS-201",
          meta: s.detail,
        }));
      const newNotes = chosen
        .filter((s) => s.kind === "note" && !noteIds.has(s.id))
        .map((s) => ({
          id: s.id,
          title: "Key terms — research methods",
          courseCode: "RM-110",
          meta: "validity, reliability, operationalization",
          date: "Today",
        }));

      return {
        ...state,
        tasks: [...state.tasks, ...newTasks],
        resources: [...state.resources, ...newResources],
        notes: [...newNotes, ...state.notes],
        intake: { ...state.intake, status: "confirmed" },
      };
    }

    case "captureAnother":
      return {
        ...state,
        intake: { docId: null, status: "idle", stage: 0, accepted: [] },
      };

    case "toggleTask":
      return {
        ...state,
        tasks: state.tasks.map((task) =>
          task.id === action.id
            ? { ...task, completed: !task.completed }
            : task,
        ),
      };

    case "toggleTool":
      return {
        ...state,
        savedToolIds: state.savedToolIds.includes(action.id)
          ? state.savedToolIds.filter((id) => id !== action.id)
          : [...state.savedToolIds, action.id],
      };

    case "useTemplate": {
      const template = DEMO_TEMPLATES.find((t) => t.id === action.templateId);

      if (!template) {
        return state;
      }

      const taskId = `tpl-${action.templateId}-${action.courseCode}`;

      return {
        ...state,
        usedTemplateIds: state.usedTemplateIds.includes(action.templateId)
          ? state.usedTemplateIds
          : [...state.usedTemplateIds, action.templateId],
        tasks: state.tasks.some((t) => t.id === taskId)
          ? state.tasks
          : [
              ...state.tasks,
              {
                id: taskId,
                title: template.taskTitle,
                courseCode: action.courseCode,
                due: "Sun, Sep 27",
                dayIndex: 6,
                completed: false,
                origin: "template",
              },
            ],
      };
    }

    case "startFocus":
      return { ...state, focus: "running" };

    case "completeFocus":
      return { ...state, focus: "done" };

    case "reset":
      return INITIAL_STATE;

    default:
      return state;
  }
}

const DemoContext = createContext<{
  state: DemoState;
  dispatch: Dispatch<DemoAction>;
} | null>(null);

export function useDemo() {
  const context = useContext(DemoContext);

  if (context === null) {
    throw new Error("useDemo must be used inside the demo provider.");
  }

  return context;
}

/** Truthful progress math — every number derives from demo state. */
export function demoProgress(state: DemoState) {
  const total = state.tasks.length;
  const completed = state.tasks.filter((t) => t.completed).length;
  const percent = total === 0 ? 0 : Math.round((completed / total) * 100);
  const focusMinutes = state.focus === "done" ? FOCUS_SESSION.minutes : 0;
  const weekly = [0, 1, 2, 3, 4, 5, 6].map(
    (day) =>
      state.tasks.filter((t) => t.completed && t.dayIndex === day).length,
  );
  const nextTask =
    [...state.tasks]
      .filter((t) => !t.completed)
      .sort((a, b) => a.dayIndex - b.dayIndex)[0] ?? null;

  return { total, completed, percent, focusMinutes, weekly, nextTask };
}

const DEMO_NAV: Array<{ view: DemoView; label: string; icon: LucideIcon }> = [
  { view: "dashboard", label: "Home", icon: LayoutDashboard },
  { view: "intake", label: "Smart Intake", icon: Inbox },
  { view: "planner", label: "Planner", icon: CalendarDays },
  { view: "resources", label: "Resources", icon: Library },
  { view: "tools", label: "AI Tools", icon: Wrench },
  { view: "templates", label: "Templates", icon: LayoutTemplate },
  { view: "brain", label: "Second Brain", icon: Brain },
  { view: "community", label: "Community", icon: Users },
  { view: "progress", label: "Progress", icon: TrendingUp },
];

const VIEWS: Record<DemoView, () => ReactNode> = {
  dashboard: DashboardView,
  intake: IntakeView,
  planner: PlannerView,
  resources: ResourcesView,
  tools: ToolsView,
  templates: TemplatesView,
  brain: BrainView,
  community: CommunityView,
  progress: ProgressView,
};

export function DemoApp() {
  const [state, dispatch] = useReducer(demoReducer, INITIAL_STATE);
  const [copilotOpen, setCopilotOpen] = useState(false);
  const [bellOpen, setBellOpen] = useState(false);
  const ActiveView = VIEWS[state.view];

  return (
    <DemoContext.Provider value={{ state, dispatch }}>
      <div className="flex h-dvh flex-col bg-bg-canvas">
        {/* Demo banner: the only chrome that is not part of the product. */}
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-b border-border-default bg-bg-surface px-4 py-2">
          <Link
            href="/"
            aria-label="Exit the demo and return to the home page"
            className="inline-flex items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
          >
            <EduConnectThemedLogo width={132} decorative />
          </Link>
          <Badge variant="brand">Live Demo</Badge>
          <p className="hidden text-caption text-text-muted md:block">
            Sample data · runs in your browser · nothing is uploaded or stored
          </p>
          <div className="ml-auto flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => dispatch({ type: "reset" })}
            >
              <RotateCcw aria-hidden="true" className="size-3.5" />
              Restart
            </Button>
            <Link
              href="/"
              className={buttonClasses({ variant: "secondary", size: "sm" })}
            >
              Exit demo
            </Link>
          </div>
        </div>

        <div className="flex min-h-0 flex-1">
          <aside className="hidden w-56 shrink-0 flex-col overflow-y-auto border-r border-border-default bg-bg-surface lg:flex">
            <nav aria-label="Demo navigation" className="p-3">
              <ul className="space-y-0.5">
                {DEMO_NAV.map((item) => {
                  const isActive = state.view === item.view;

                  return (
                    <li key={item.view}>
                      <button
                        type="button"
                        aria-current={isActive ? "page" : undefined}
                        onClick={() =>
                          dispatch({ type: "navigate", view: item.view })
                        }
                        className={cn(
                          "flex h-10 w-full items-center gap-3 rounded-md px-3 text-body font-medium transition-colors",
                          "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                          isActive
                            ? "bg-brand-primary text-white shadow-glow-sm"
                            : "text-text-secondary hover:bg-bg-interactive hover:text-text-primary",
                        )}
                      >
                        <item.icon aria-hidden="true" className="size-4" />
                        {item.label}
                      </button>
                    </li>
                  );
                })}
              </ul>
            </nav>

            <div className="mx-3 mt-auto rounded-lg border border-brand-primary/30 bg-linear-to-b from-bg-interactive to-bg-surface p-4">
              <p className="text-label text-brand-primary">
                {JOURNEY_CARD.title}
              </p>
              <p className="mt-1 text-caption text-text-secondary">
                “{JOURNEY_CARD.quote}”
              </p>
              <svg
                aria-hidden="true"
                viewBox="0 0 160 36"
                fill="none"
                className="mt-3 w-full"
              >
                <polyline
                  points="4,30 32,26 58,28 86,18 114,20 140,10 156,6"
                  stroke="var(--brand-primary)"
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
                {[
                  [4, 30],
                  [58, 28],
                  [114, 20],
                  [156, 6],
                ].map(([x, y]) => (
                  <circle
                    key={`${x}-${y}`}
                    cx={x}
                    cy={y}
                    r="3"
                    fill="var(--brand-focus)"
                  />
                ))}
              </svg>
            </div>

            <div className="m-3 rounded-lg border border-border-subtle p-4">
              <p className="text-label text-text-primary">Hobby rhythm</p>
              <ul className="mt-2.5 grid grid-cols-7 gap-1">
                {HOBBY_RHYTHM.map((entry) => (
                  <li
                    key={entry.day}
                    className="flex flex-col items-center gap-1"
                  >
                    <span className="text-caption text-text-muted">
                      {entry.day.charAt(0)}
                    </span>
                    <span
                      title={entry.hobby}
                      className={cn(
                        "size-2 rounded-full",
                        entry.hobby
                          ? "bg-status-research"
                          : "bg-bg-interactive",
                      )}
                    />
                  </li>
                ))}
              </ul>
              <p className="mt-2 text-caption text-text-muted">
                Sample week · optional and private in the product.
              </p>
            </div>
          </aside>

          <div className="flex min-w-0 flex-1 flex-col">
            <div className="relative flex h-14 shrink-0 items-center gap-3 border-b border-border-default bg-bg-surface px-4">
              <div className="hidden min-w-0 flex-1 md:block">
                <DemoSearch />
              </div>
              <span className="hidden shrink-0 items-center gap-2 rounded-md border border-border-default px-3 py-1.5 text-caption text-text-secondary sm:inline-flex">
                <CalendarDays
                  aria-hidden="true"
                  className="size-3.5 text-text-muted"
                />
                {DEMO_PERSONA.term}
              </span>
              <div className="ml-auto flex shrink-0 items-center gap-2.5">
                <button
                  type="button"
                  aria-label="Notifications"
                  aria-expanded={bellOpen}
                  onClick={() => setBellOpen((open) => !open)}
                  className="flex size-9 items-center justify-center rounded-full text-text-muted transition-colors hover:bg-bg-interactive hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
                >
                  <Bell aria-hidden="true" className="size-4.5" />
                </button>
                {bellOpen ? (
                  <div className="absolute right-4 top-14 z-30 w-64 rounded-md border border-border-default bg-bg-elevated p-4 shadow-glow-sm">
                    <p className="text-body font-medium text-text-primary">
                      You're all caught up
                    </p>
                    <p className="mt-1 text-caption text-text-muted">
                      Notifications arrive with the full product — the demo
                      never fakes an unread badge.
                    </p>
                  </div>
                ) : null}
                <div className="flex items-center gap-2 rounded-full border border-border-default py-1 pl-1 pr-3">
                  <span
                    aria-hidden="true"
                    className="flex size-7 items-center justify-center rounded-full bg-brand-primary text-caption font-semibold text-white"
                  >
                    {DEMO_PERSONA.name.charAt(0)}
                  </span>
                  <span className="hidden lg:block">
                    <span className="block text-caption font-medium leading-tight text-text-primary">
                      {DEMO_PERSONA.name} · sample
                    </span>
                    <span className="block text-caption leading-tight text-text-muted">
                      Computer Science
                    </span>
                  </span>
                </div>
                <ThemeToggle />
              </div>
            </div>

            <nav
              aria-label="Demo navigation (compact)"
              className="shrink-0 border-b border-border-subtle bg-bg-surface px-3 py-2 lg:hidden"
            >
              <ul className="flex gap-1 overflow-x-auto">
                {DEMO_NAV.map((item) => {
                  const isActive = state.view === item.view;

                  return (
                    <li key={item.view} className="shrink-0">
                      <button
                        type="button"
                        aria-current={isActive ? "page" : undefined}
                        onClick={() =>
                          dispatch({ type: "navigate", view: item.view })
                        }
                        className={cn(
                          "rounded-full px-3 py-1.5 text-caption font-medium",
                          isActive
                            ? "bg-bg-interactive text-brand-primary"
                            : "text-text-secondary hover:text-text-primary",
                        )}
                      >
                        {item.label}
                      </button>
                    </li>
                  );
                })}
              </ul>
            </nav>

            <main
              aria-label="Demo workspace"
              className="min-h-0 flex-1 overflow-y-auto p-4 pb-28 lg:p-6"
            >
              <ActiveView />
            </main>
          </div>
        </div>

        {copilotOpen ? (
          <div className="fixed bottom-24 right-5 z-50 w-72 max-w-[calc(100vw-2.5rem)]">
            <Card className="border-status-ai/30 bg-bg-surface shadow-glow-sm">
              <CardHeader className="mb-2">
                <CardTitle as="h3">Copilot</CardTitle>
              </CardHeader>
              <CardContent>
                The real Copilot lives in the signed-in app and answers from
                your actual workspace. The demo keeps this one collapsed.
              </CardContent>
            </Card>
          </div>
        ) : null}
        <CopilotTrigger
          variant="pill"
          isOpen={copilotOpen}
          onToggle={() => setCopilotOpen((open) => !open)}
        />
      </div>
    </DemoContext.Provider>
  );
}
