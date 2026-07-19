/**
 * Timezone-safe planner date math. Every helper works from IANA-zone local
 * parts of real instants - never from the device clock's UTC offset - so
 * daylight-saving transitions cannot shift a task or session into the
 * wrong local day (doc 07; same anchoring rule as the dashboard).
 */

/** YYYY-MM-DD of an instant in the given IANA timezone. */
export function localDateOf(instant: Date, timezone: string): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(instant);
}

/** Pure calendar-day arithmetic on a YYYY-MM-DD string. */
export function addDays(date: string, days: number): string {
  const [year = 0, month = 1, day = 1] = date.split("-").map(Number);
  const shifted = new Date(Date.UTC(year, month - 1, day + days));

  return shifted.toISOString().slice(0, 10);
}

/** Monday of the calendar week containing the given YYYY-MM-DD. */
export function mondayOf(date: string): string {
  const [year = 0, month = 1, day = 1] = date.split("-").map(Number);
  const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

  return addDays(date, weekday === 0 ? -6 : 1 - weekday);
}

/** The seven dates of the week starting at weekStart. */
export function weekDates(weekStart: string): string[] {
  return Array.from({ length: 7 }, (_, index) => addDays(weekStart, index));
}

/** Minutes past local midnight for an instant in the given timezone. */
export function minutesIntoDay(instant: string, timezone: string): number {
  const parts = new Intl.DateTimeFormat("en-GB", {
    timeZone: timezone,
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(new Date(instant));

  const hour = Number(parts.find((part) => part.type === "hour")?.value ?? 0);
  const minute = Number(
    parts.find((part) => part.type === "minute")?.value ?? 0,
  );

  return hour * 60 + minute;
}

/** Whether an instant falls on the given local date in the timezone. */
export function isOnLocalDate(
  instant: string,
  date: string,
  timezone: string,
): boolean {
  return localDateOf(new Date(instant), timezone) === date;
}

/** Short local time, e.g. "9:30 AM". */
export function formatLocalTime(instant: string, timezone: string): string {
  return new Intl.DateTimeFormat("en-US", {
    timeZone: timezone,
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(instant));
}

/** HH:mm (24h) value for an `<input type="time">`, in the given timezone. */
export function localTimeInputValue(instant: string, timezone: string): string {
  const parts = new Intl.DateTimeFormat("en-GB", {
    timeZone: timezone,
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(new Date(instant));

  const hour = parts.find((part) => part.type === "hour")?.value ?? "00";
  const minute = parts.find((part) => part.type === "minute")?.value ?? "00";

  return `${hour}:${minute}`;
}

/** "9:30 AM - 10:20 AM" for a session in the timezone. */
export function formatLocalTimeRange(
  startsAt: string,
  endsAt: string,
  timezone: string,
): string {
  return `${formatLocalTime(startsAt, timezone)} to ${formatLocalTime(endsAt, timezone)}`;
}

/** "Mon", "Tue", … for a YYYY-MM-DD calendar date. */
export function weekdayShort(date: string): string {
  const [year = 0, month = 1, day = 1] = date.split("-").map(Number);

  return new Intl.DateTimeFormat("en-US", {
    timeZone: "UTC",
    weekday: "short",
  }).format(new Date(Date.UTC(year, month - 1, day)));
}

/** "Jul 13 - Jul 19, 2026" for a week starting at weekStart. */
export function formatWeekRange(weekStart: string): string {
  const end = addDays(weekStart, 6);
  const format = (date: string, withYear: boolean) => {
    const [year = 0, month = 1, day = 1] = date.split("-").map(Number);

    return new Intl.DateTimeFormat("en-US", {
      timeZone: "UTC",
      month: "short",
      day: "numeric",
      ...(withYear ? { year: "numeric" } : {}),
    }).format(new Date(Date.UTC(year, month - 1, day)));
  };

  return `${format(weekStart, false)} to ${format(end, true)}`;
}

/** "Thu, Jul 17" for a YYYY-MM-DD calendar date. */
export function formatDayHeading(date: string): string {
  const [year = 0, month = 1, day = 1] = date.split("-").map(Number);

  return new Intl.DateTimeFormat("en-US", {
    timeZone: "UTC",
    weekday: "short",
    month: "short",
    day: "numeric",
  }).format(new Date(Date.UTC(year, month - 1, day)));
}

/** Whole minutes between two instants, never negative. */
export function durationMinutes(startsAt: string, endsAt: string): number {
  const elapsed =
    (new Date(endsAt).getTime() - new Date(startsAt).getTime()) / 60_000;

  return Math.max(0, Math.round(elapsed));
}

/**
 * Second-precision RFC3339 UTC instant the planner write contract accepts
 * (PlannerOffsetDateTime rejects milliseconds).
 */
export function toApiInstant(instant: Date): string {
  return `${instant.toISOString().slice(0, 19)}Z`;
}

/**
 * Instant for local wall-clock date+time in the BROWSER's zone. Planner
 * inputs are entered in the user's own timezone by design.
 */
export function instantFromLocalInput(date: string, time: string): Date {
  return new Date(`${date}T${time}`);
}

/** Stable palette slot for a course id so week blocks stay consistent. */
export function courseColorSlot(courseId: string, slots: number): number {
  let hash = 0;

  for (const char of courseId) {
    hash = (hash * 31 + char.charCodeAt(0)) % 997;
  }

  return hash % slots;
}
