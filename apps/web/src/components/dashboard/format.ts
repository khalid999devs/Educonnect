import type { Dashboard } from "@/lib/api/dashboard";

/**
 * All time math anchors on the aggregate's own timeframe (server truth in
 * the requested timezone) - never the client clock.
 */

export function browserTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
  } catch {
    return "UTC";
  }
}

export function formatDueAt(dueAt: string | null, timezone: string): string {
  if (dueAt === null) {
    return "No due date";
  }

  return new Intl.DateTimeFormat("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    timeZone: timezone,
  }).format(new Date(dueAt));
}

export function formatTime(value: string | null, timezone: string): string {
  if (value === null) {
    return "-";
  }

  return new Intl.DateTimeFormat("en-US", {
    hour: "numeric",
    minute: "2-digit",
    timeZone: timezone,
  }).format(new Date(value));
}

/** Whole-day difference between an instant and the aggregate's "today". */
export function daysFromToday(
  dueAt: string | null,
  dashboard: Dashboard,
): number | null {
  if (dueAt === null) {
    return null;
  }

  const dayFormatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: dashboard.timeframe.timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
  const dueDay = dayFormatter.format(new Date(dueAt));
  const diff =
    (Date.parse(dueDay) - Date.parse(dashboard.timeframe.today)) / 86_400_000;

  return Math.round(diff);
}

export function dueNote(
  dueAt: string | null,
  dashboard: Dashboard,
): string | null {
  const days = daysFromToday(dueAt, dashboard);

  if (days === null) {
    return null;
  }

  if (days < 0) {
    return days === -1 ? "1 day overdue" : `${-days} days overdue`;
  }

  if (days === 0) {
    return "Due today";
  }

  if (days === 1) {
    return "Due tomorrow";
  }

  return `${days} days left`;
}

export function weekdayLetter(date: string): string {
  return new Intl.DateTimeFormat("en-US", {
    weekday: "narrow",
    timeZone: "UTC",
  }).format(new Date(`${date}T00:00:00Z`));
}
