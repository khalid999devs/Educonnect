/** Compact relative time (e.g. "2 hours ago") for community and mentor
 * timestamps. Uses the browser's Intl formatter; returns "" on invalid input. */
export function formatRelativeTime(iso: string): string {
  const then = new Date(iso).getTime();

  if (Number.isNaN(then)) {
    return "";
  }

  const seconds = Math.round((Date.now() - then) / 1000);
  const formatter = new Intl.RelativeTimeFormat("en", { numeric: "auto" });

  const divisions: Array<{
    amount: number;
    unit: Intl.RelativeTimeFormatUnit;
  }> = [
    { amount: 60, unit: "second" },
    { amount: 60, unit: "minute" },
    { amount: 24, unit: "hour" },
    { amount: 30, unit: "day" },
    { amount: 12, unit: "month" },
    { amount: Number.POSITIVE_INFINITY, unit: "year" },
  ];

  let value = seconds;
  for (const division of divisions) {
    if (Math.abs(value) < division.amount) {
      return formatter.format(-value, division.unit);
    }
    value = Math.round(value / division.amount);
  }

  return formatter.format(-value, "year");
}
