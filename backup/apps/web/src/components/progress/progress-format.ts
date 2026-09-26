/**
 * Date and count helpers shared by the Progress surfaces.
 *
 * The API returns plain calendar dates (`YYYY-MM-DD`) already resolved in the
 * student's own timezone. Formatting them through `new Date(iso)` would
 * reinterpret them as UTC midnight and shift the label a day backwards west of
 * Greenwich, so every formatter below pins `timeZone: "UTC"`.
 */

function parseCalendarDate(date: string): Date | null {
  const parsed = new Date(`${date}T00:00:00Z`);

  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function format(date: string, options: Intl.DateTimeFormatOptions): string {
  const parsed = parseCalendarDate(date);

  if (parsed === null) {
    return date;
  }

  return new Intl.DateTimeFormat("en-GB", {
    ...options,
    timeZone: "UTC",
  }).format(parsed);
}

/** "Mon 21 Jul" - the milestone feed and rhythm strip label. */
export function formatDayLabel(date: string): string {
  return format(date, { weekday: "short", day: "numeric", month: "short" });
}

/** "21 Jul 2026" - timeframe bounds. */
export function formatDateLabel(date: string): string {
  return format(date, { day: "numeric", month: "short", year: "numeric" });
}

/** Single letter weekday for dense chart axes. */
export function formatWeekdayInitial(date: string): string {
  return format(date, { weekday: "narrow" });
}

/** "2 Jul" - dense chart axes on longer windows. */
export function formatShortDate(date: string): string {
  return format(date, { day: "numeric", month: "short" });
}

/** "1 task" / "3 tasks" - counts always read as real quantities. */
export function pluralize(count: number, one: string, many: string): string {
  return `${count} ${count === 1 ? one : many}`;
}

/** "25 min" / "1 h 5 min". Focus time is always a measured total. */
export function formatMinutes(minutes: number): string {
  if (minutes < 60) {
    return `${minutes} min`;
  }

  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;

  return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}
