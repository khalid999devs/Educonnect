import { cn } from "@educonnect/ui";

/*
 * Bespoke, license-clean marketing illustrations in the reference art
 * style (deep navy, indigo glow, orbiting accents). Authored as inline SVG
 * on semantic tokens so both themes render correctly; all motion sits on
 * motion-safe utilities. Decorative only - always aria-hidden.
 */

export function CapIllustration({ className }: { className?: string }) {
  return (
    <div aria-hidden="true" className={cn("relative", className)}>
      <div className="absolute inset-[12%] rounded-full bg-brand-primary/20 blur-2xl motion-safe:animate-pulse-soft" />

      <svg viewBox="0 0 260 240" fill="none" className="relative block w-full">
        {/* Orbit ring */}
        <g
          className="origin-center motion-safe:animate-spin-slow"
          stroke="var(--brand-focus)"
          strokeOpacity="0.35"
        >
          <ellipse cx="130" cy="122" rx="112" ry="86" strokeDasharray="3 7" />
          <circle cx="130" cy="36" r="4" fill="var(--brand-focus)" />
          <circle cx="242" cy="122" r="3" fill="var(--status-ai)" />
        </g>

        {/* Book stack */}
        <g>
          <rect
            x="70"
            y="160"
            width="120"
            height="22"
            rx="6"
            fill="var(--bg-interactive)"
            stroke="var(--border-strong)"
          />
          <rect
            x="82"
            y="138"
            width="96"
            height="22"
            rx="6"
            fill="var(--bg-elevated)"
            stroke="var(--border-strong)"
          />
          <line
            x1="96"
            y1="149"
            x2="164"
            y2="149"
            stroke="var(--text-muted)"
            strokeOpacity="0.5"
            strokeWidth="2"
            strokeLinecap="round"
          />
        </g>

        {/* Graduation cap */}
        <g>
          <polygon
            points="130,74 210,104 130,134 50,104"
            fill="var(--brand-primary)"
          />
          <polygon
            points="130,74 210,104 130,134 50,104"
            fill="white"
            fillOpacity="0.12"
          />
          <path
            d="M92 118v22c0 10 17 18 38 18s38-8 38-18v-22l-38 14z"
            fill="var(--brand-primary-hover)"
          />
          <line
            x1="210"
            y1="104"
            x2="210"
            y2="146"
            stroke="var(--status-warning)"
            strokeWidth="3"
            strokeLinecap="round"
          />
          <circle cx="210" cy="152" r="5" fill="var(--status-warning)" />
        </g>

        {/* Sparkles */}
        <g fill="var(--status-ai)">
          <path d="M54 52l3.2 8 8 3.2-8 3.2-3.2 8-3.2-8-8-3.2 8-3.2z" />
        </g>
        <g fill="var(--brand-focus)">
          <path d="M214 44l2.4 6 6 2.4-6 2.4-2.4 6-2.4-6-6-2.4 6-2.4z" />
        </g>
        <g fill="var(--status-research)">
          <circle cx="36" cy="150" r="4" />
        </g>
      </svg>

      <span className="absolute left-[4%] top-[16%] rounded-md border border-border-default bg-bg-surface p-2 shadow-glow-sm motion-safe:animate-float">
        <svg viewBox="0 0 24 24" fill="none" className="size-4">
          <path
            d="M4 19V6a2 2 0 0 1 2-2h13v13H6a2 2 0 0 0-2 2zm0 0a2 2 0 0 0 2 2h13"
            stroke="var(--status-info)"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </span>
      <span className="absolute bottom-[10%] right-[6%] rounded-md border border-border-default bg-bg-surface p-2 shadow-glow-sm motion-safe:animate-float-delayed">
        <svg viewBox="0 0 24 24" fill="none" className="size-4">
          <path
            d="M12 3l2.1 5.2L19 10l-4.9 1.8L12 17l-2.1-5.2L5 10l4.9-1.8z"
            stroke="var(--status-ai)"
            strokeWidth="2"
            strokeLinejoin="round"
          />
        </svg>
      </span>
    </div>
  );
}

export function BookIllustration({ className }: { className?: string }) {
  return (
    <div aria-hidden="true" className={cn("relative", className)}>
      <div className="absolute inset-[14%] rounded-full bg-brand-primary/20 blur-2xl motion-safe:animate-pulse-soft" />

      <svg viewBox="0 0 240 190" fill="none" className="relative block w-full">
        <g
          className="origin-center motion-safe:animate-spin-slow"
          stroke="var(--brand-focus)"
          strokeOpacity="0.3"
        >
          <ellipse cx="120" cy="100" rx="104" ry="70" strokeDasharray="3 7" />
          <circle cx="120" cy="30" r="3.5" fill="var(--brand-focus)" />
        </g>

        {/* Open book */}
        <g>
          <path
            d="M120 66c-14-10-34-14-52-12v76c18-2 38 2 52 12 14-10 34-14 52-12V54c-18-2-38 2-52 12z"
            fill="var(--bg-elevated)"
            stroke="var(--border-strong)"
            strokeWidth="2"
          />
          <path d="M120 66v76" stroke="var(--border-strong)" strokeWidth="2" />
          <g
            stroke="var(--brand-primary)"
            strokeOpacity="0.7"
            strokeWidth="2.5"
            strokeLinecap="round"
          >
            <path d="M82 78h24M82 92h26M82 106h20" />
            <path d="M134 78h24M134 92h26M134 106h20" />
          </g>
        </g>

        {/* Pen */}
        <g transform="rotate(-32 176 58)">
          <rect
            x="168"
            y="26"
            width="14"
            height="52"
            rx="6"
            fill="var(--brand-primary)"
          />
          <polygon points="168,78 182,78 175,94" fill="var(--status-warning)" />
        </g>

        <g fill="var(--status-ai)">
          <path d="M42 40l2.8 7 7 2.8-7 2.8-2.8 7-2.8-7-7-2.8 7-2.8z" />
        </g>
        <g fill="var(--status-research)">
          <circle cx="208" cy="140" r="4" />
        </g>
      </svg>
    </div>
  );
}
