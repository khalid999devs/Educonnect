/** Formats an ISO timestamp as an absolute, unambiguous UTC-based string. */
export function formatDateTime(iso: string | null): string {
  if (iso === null) {
    return "—";
  }

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

/** Turns a namespaced key (`users.suspend`) into a readable label. */
export function humanizeKey(key: string): string {
  return key
    .split(/[._-]+/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}
