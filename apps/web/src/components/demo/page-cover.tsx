import { Badge } from "@educonnect/ui";
import Image from "next/image";
import type { ReactNode } from "react";

/**
 * Reference-style page cover band: full-bleed CC0 photo, legibility
 * overlays, eyebrow badge, title, subtitle, and an optional action row.
 */
export function PageCover({
  photo,
  eyebrow,
  title,
  subtitle,
  action,
}: {
  photo: string;
  eyebrow?: string;
  title: string;
  subtitle: string;
  action?: ReactNode;
}) {
  return (
    <div className="relative min-h-44 overflow-hidden rounded-xl border border-border-default">
      <Image
        src={photo}
        alt=""
        fill
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
      <div className="relative max-w-xl space-y-2.5 p-6 lg:p-7">
        {eyebrow ? <Badge variant="brand">{eyebrow}</Badge> : null}
        <h2 className="text-h2 text-text-primary">{title}</h2>
        <p className="text-body-lg text-text-secondary">{subtitle}</p>
        {action ? <div className="pt-1">{action}</div> : null}
      </div>
    </div>
  );
}
