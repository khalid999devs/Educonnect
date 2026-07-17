"use client";

import { Alert, ErrorState, Skeleton } from "@educonnect/ui";
import { useCallback, useEffect, useState } from "react";

import {
  getDashboard,
  type Dashboard,
  type DashboardTask,
} from "@/lib/api/dashboard";
import { ApiError } from "@/lib/api/http";
import { createLinkIntake } from "@/lib/api/intake";
import { completeTask } from "@/lib/api/planner";
import { saveTool, unsaveTool } from "@/lib/api/tools";
import { browserTimezone } from "./format";
import {
  CoverSection,
  ProgressSection,
  QuickIntakeSection,
  RhythmSection,
  SecondBrainSection,
  TodaySection,
  ToolsSection,
  WhatsNextSection,
} from "./dashboard-sections";

function greetingForHour(hour: number): string {
  if (hour < 5) {
    return "Good night";
  }

  if (hour < 12) {
    return "Good morning";
  }

  if (hour < 18) {
    return "Good afternoon";
  }

  return "Good evening";
}

function LoadingSkeleton() {
  return (
    <div className="space-y-4" aria-hidden="true">
      <Skeleton className="min-h-52 rounded-xl" />
      <div className="grid gap-4 xl:grid-cols-3">
        <Skeleton className="h-64 rounded-lg" />
        <Skeleton className="h-64 rounded-lg" />
        <Skeleton className="h-64 rounded-lg" />
      </div>
      <div className="grid gap-4 xl:grid-cols-2">
        <Skeleton className="h-72 rounded-lg" />
        <Skeleton className="h-72 rounded-lg" />
      </div>
    </div>
  );
}

export function DashboardView() {
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyTaskId, setBusyTaskId] = useState<string | null>(null);
  const [busyToolId, setBusyToolId] = useState<string | null>(null);
  const [captureBusy, setCaptureBusy] = useState(false);
  const [greeting] = useState(() => greetingForHour(new Date().getHours()));

  const load = useCallback(async () => {
    setLoadError(null);

    try {
      setDashboard(await getDashboard(browserTimezone()));
    } catch (caught) {
      setLoadError(
        caught instanceof ApiError
          ? caught.message
          : "Could not reach the server.",
      );
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const runAction = useCallback(
    async (action: () => Promise<void>): Promise<boolean> => {
      setActionError(null);

      try {
        await action();
        await load();

        return true;
      } catch (caught) {
        setActionError(
          caught instanceof ApiError
            ? caught.message
            : "Could not reach the server.",
        );

        return false;
      }
    },
    [load],
  );

  const onCompleteTask = useCallback(
    async (task: DashboardTask) => {
      setBusyTaskId(task.id);
      await runAction(() => completeTask(task.id));
      setBusyTaskId(null);
    },
    [runAction],
  );

  const onToggleSave = useCallback(
    async (toolId: string, saved: boolean) => {
      setBusyToolId(toolId);
      await runAction(() => (saved ? unsaveTool(toolId) : saveTool(toolId)));
      setBusyToolId(null);
    },
    [runAction],
  );

  const onCaptureLink = useCallback(
    async (url: string) => {
      setCaptureBusy(true);
      const succeeded = await runAction(() => createLinkIntake(url));
      setCaptureBusy(false);

      return succeeded;
    },
    [runAction],
  );

  if (loadError !== null) {
    return (
      <ErrorState
        title="Could not load your dashboard"
        description={loadError}
        onRetry={() => void load()}
      />
    );
  }

  if (dashboard === null) {
    return <LoadingSkeleton />;
  }

  return (
    <div className="space-y-4">
      {actionError ? (
        <Alert variant="error" title="That didn't save">
          {actionError}
        </Alert>
      ) : null}

      {/* Approved hierarchy (doc 04): cover → one Quick Intake → What's
          Next → tools → today → Second Brain → one progress region →
          personal rhythm. The single Copilot trigger is owned by the app
          shell. */}
      <CoverSection dashboard={dashboard} greeting={greeting} />

      <div className="grid gap-4 xl:grid-cols-3">
        <QuickIntakeSection
          dashboard={dashboard}
          busy={captureBusy}
          onCaptureLink={onCaptureLink}
        />
        <WhatsNextSection
          dashboard={dashboard}
          busyTaskId={busyTaskId}
          onCompleteTask={(task) => void onCompleteTask(task)}
        />
        <ToolsSection
          dashboard={dashboard}
          busyToolId={busyToolId}
          onToggleSave={(toolId, saved) => void onToggleSave(toolId, saved)}
        />
      </div>

      <TodaySection dashboard={dashboard} />

      <div className="grid gap-4 xl:grid-cols-2">
        <SecondBrainSection dashboard={dashboard} />
        <ProgressSection dashboard={dashboard} />
      </div>

      <RhythmSection dashboard={dashboard} />
    </div>
  );
}
