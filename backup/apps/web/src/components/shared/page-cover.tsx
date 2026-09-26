import { Badge, cn } from "@educonnect/ui";
import Image from "next/image";
import type { ReactNode } from "react";

/**
 * Reference-style page cover band: full-bleed CC0 photo, legibility overlays,
 * eyebrow badge, title, subtitle, an optional action row, and an optional
 * full-width `search` row.
 *
 * Contrast ruling (build brief 6): the cover gradient stays at
 * `to-bg-canvas/25` and any chrome placed over the faded right side supplies
 * its own `bg-bg-surface/85 backdrop-blur-sm` backing. `SearchBar` with
 * `tone="overlay"` does exactly that, which is why the search slot clears
 * 4.5:1 in both themes without changing the photo treatment.
 *
 * The search slot is a sibling row OUTSIDE the `max-w-xl` text block, so it
 * spans the full cover width.
 */
export type PageCoverProps = {
  photo: string;
  eyebrow?: string;
  title: string;
  /** Node rather than string so a cover can offer an inline recovery link
   * (for example "Finish setup") in place of a plain description. */
  subtitle: ReactNode;
  action?: ReactNode;
  /** Full-width row beneath the text block. Intended for a search bar. */
  search?: ReactNode;
  /** `2` keeps the demo's in-page heading semantics; real pages use `1`. */
  headingLevel?: 1 | 2;
  /** Taller band on large screens, matching the app section covers. */
  tall?: boolean;
  priority?: boolean;
};

export function PageCover({
  photo,
  eyebrow,
  title,
  subtitle,
  action,
  search,
  headingLevel = 2,
  tall = false,
  priority = false,
}: PageCoverProps) {
  const Heading = headingLevel === 1 ? "h1" : "h2";

  return (
    <div
      className={cn(
        "relative min-h-44 overflow-hidden rounded-xl border border-border-default",
        tall ? "lg:min-h-52" : null,
      )}
    >
      <Image
        src={photo}
        alt=""
        fill
        priority={priority}
        sizes="(max-width: 1024px) 100vw, 1100px"
        className="object-cover object-center"
      />
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-linear-to-r from-bg-canvas/95 via-bg-canvas/80 to-bg-canvas/25"
      />
      <div
        aria-hidden="true"
        className="absolute inset-x-0 bottom-0 h-14 bg-linear-to-t from-bg-canvas/70 to-transparent"
      />
      <div className="relative p-6 lg:p-7">
        <div className="max-w-xl space-y-2.5">
          {eyebrow ? <Badge variant="brand">{eyebrow}</Badge> : null}
          <Heading className="text-h2 text-text-primary">{title}</Heading>
          <p className="text-body-lg text-text-secondary">{subtitle}</p>
          {action ? <div className="pt-1">{action}</div> : null}
        </div>
        {search ? <div className="mt-4 w-full">{search}</div> : null}
      </div>
    </div>
  );
}
