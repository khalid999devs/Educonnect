import { Badge, cn } from "@educonnect/ui";
import {
  Brain,
  CalendarDays,
  CircleCheck,
  FileText,
  Sparkles,
} from "lucide-react";

/**
 * Static, honest product mockup for marketing sections: the dashboard's
 * shape rendered from the same sample dataset the Live Demo uses, labeled
 * as sample data. Numbers are the demo seed (1 of 3 tasks completed) — no
 * streaks, no vs-last-week claims.
 */
export function ProductPreview({ className }: { className?: string }) {
  const weekly = [1, 0, 0, 0, 0, 0, 0];
  const maxWeekly = Math.max(1, ...weekly);

  return (
    <div className={cn("relative", className)}>
      <div
        aria-hidden="true"
        className="absolute -inset-8 rounded-[3rem] bg-brand-primary/15 blur-3xl"
      />

      <div className="relative overflow-hidden rounded-lg border border-border-default bg-bg-surface shadow-glow-sm">
        <div className="flex items-center gap-2 border-b border-border-subtle bg-bg-subtle px-4 py-2.5">
          <span aria-hidden="true" className="flex gap-1.5">
            <span className="size-2.5 rounded-full bg-border-strong" />
            <span className="size-2.5 rounded-full bg-border-strong" />
            <span className="size-2.5 rounded-full bg-border-strong" />
          </span>
          <p className="text-caption text-text-muted">
            app.educonnect · dashboard
          </p>
          <Badge variant="neutral" className="ml-auto">
            Sample data
          </Badge>
        </div>

        <div className="space-y-4 p-5">
          <div>
            <p className="text-h4 text-text-primary">Welcome back, Sam 👋</p>
            <p className="text-caption text-text-muted">
              B.Sc. Computer Science · Fall 2026 · 2 active courses
            </p>
          </div>

          <div className="grid grid-cols-3 gap-2.5">
            <div className="rounded-md border border-border-subtle bg-bg-canvas p-3">
              <p className="text-caption text-text-muted">Today's classes</p>
              <p className="text-h3 tabular-nums text-text-primary">1</p>
            </div>
            <div className="rounded-md border border-border-subtle bg-bg-canvas p-3">
              <p className="text-caption text-text-muted">Open tasks</p>
              <p className="text-h3 tabular-nums text-text-primary">2</p>
            </div>
            <div className="rounded-md border border-border-subtle bg-bg-canvas p-3">
              <p className="text-caption text-text-muted">This week</p>
              <p className="text-h3 tabular-nums text-brand-primary">1/3</p>
            </div>
          </div>

          <div className="rounded-md border border-border-subtle bg-bg-canvas p-3">
            <div className="flex items-center justify-between">
              <p className="text-caption font-medium text-text-secondary">
                Weekly progress
              </p>
              <p className="text-caption tabular-nums text-text-muted">
                1 of 3 tasks done
              </p>
            </div>
            <div
              aria-hidden="true"
              className="mt-2 flex h-12 items-end gap-1.5"
            >
              {weekly.map((count, index) => (
                <span
                  key={index}
                  className={cn(
                    "flex-1 rounded-sm",
                    count > 0 ? "bg-brand-primary" : "bg-bg-interactive",
                  )}
                  style={{ height: `${(count / maxWeekly) * 36 + 6}px` }}
                />
              ))}
            </div>
          </div>

          <div className="space-y-2">
            <p className="text-caption font-medium text-text-secondary">
              Upcoming
            </p>
            <div className="flex items-center gap-2.5 rounded-md border border-border-subtle bg-bg-canvas px-3 py-2">
              <FileText
                aria-hidden="true"
                className="size-4 shrink-0 text-status-deadline"
              />
              <p className="min-w-0 flex-1 truncate text-caption text-text-primary">
                Reading response — Babbie ch. 4
              </p>
              <p className="text-caption tabular-nums text-text-muted">Thu</p>
            </div>
            <div className="flex items-center gap-2.5 rounded-md border border-border-subtle bg-bg-canvas px-3 py-2">
              <CircleCheck
                aria-hidden="true"
                className="size-4 shrink-0 text-status-success"
              />
              <p className="min-w-0 flex-1 truncate text-caption text-text-primary">
                Set up course workspaces
              </p>
              <p className="text-caption text-status-success">Done</p>
            </div>
          </div>
        </div>
      </div>

      <span
        aria-hidden="true"
        className="absolute -left-5 top-16 hidden rounded-md border border-border-default bg-bg-surface p-2.5 shadow-glow-sm motion-safe:animate-float sm:block"
      >
        <Brain className="size-5 text-status-research" />
      </span>
      <span
        aria-hidden="true"
        className="absolute -right-4 top-40 hidden rounded-md border border-border-default bg-bg-surface p-2.5 shadow-glow-sm motion-safe:animate-float-delayed sm:block"
      >
        <Sparkles className="size-5 text-status-ai" />
      </span>
      <span
        aria-hidden="true"
        className="absolute -bottom-4 left-10 hidden rounded-md border border-border-default bg-bg-surface p-2.5 shadow-glow-sm motion-safe:animate-float sm:block"
      >
        <CalendarDays className="size-5 text-brand-primary" />
      </span>
    </div>
  );
}
