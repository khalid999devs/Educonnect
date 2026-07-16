"use client";

const WEEK_LABELS = ["M", "T", "W", "T", "F", "S", "S"];

/** Area line chart of tasks completed per weekday, from demo state. */
export function WeeklyChart({ weekly }: { weekly: number[] }) {
  const max = Math.max(1, ...weekly);
  const points = weekly.map(
    (value, index) =>
      [12 + index * (196 / 6), 46 - (value / max) * 32] as const,
  );
  const line = points.map((point) => point.join(",")).join(" ");
  const summary = weekly
    .map((value, index) => `${WEEK_LABELS[index]} ${value}`)
    .join(", ");

  return (
    <svg
      viewBox="0 0 220 62"
      role="img"
      aria-label={`Tasks completed per day this week: ${summary}`}
      className="w-full"
    >
      <defs>
        <linearGradient id="demo-progress-fill" x1="0" y1="0" x2="0" y2="1">
          <stop
            offset="0"
            stopColor="var(--brand-primary)"
            stopOpacity="0.32"
          />
          <stop offset="1" stopColor="var(--brand-primary)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon
        points={`12,50 ${line} 208,50`}
        fill="url(#demo-progress-fill)"
      />
      <polyline
        points={line}
        fill="none"
        stroke="var(--brand-primary)"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      {points.map((point, index) => (
        <circle
          key={WEEK_LABELS[index]! + index}
          cx={point[0]}
          cy={point[1]}
          r="3"
          fill="var(--brand-focus)"
        />
      ))}
      {points.map((point, index) => (
        <text
          key={`label-${WEEK_LABELS[index]!}${index}`}
          x={point[0]}
          y={59}
          textAnchor="middle"
          fontSize="7.5"
          fill="var(--text-muted)"
        >
          {WEEK_LABELS[index]}
        </text>
      ))}
    </svg>
  );
}
