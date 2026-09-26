import Link from "next/link";
import type { ComponentProps } from "react";

import { EduConnectLogo, type EduConnectLogoProps } from "./educonnect-logo";

type EduConnectLogoLinkProps = {
  href?: ComponentProps<typeof Link>["href"];
  logoProps?: EduConnectLogoProps;
  className?: string;
  ariaLabel?: string;
};

export function EduConnectLogoLink({
  href = "/",
  logoProps,
  className,
  ariaLabel = "Go to EduConnect home",
}: EduConnectLogoLinkProps) {
  return (
    <Link
      href={href}
      aria-label={ariaLabel}
      className={[
        "inline-flex items-center rounded-md",
        "focus-visible:outline-none",
        "focus-visible:ring-2",
        "focus-visible:ring-brand-focus",
        "focus-visible:ring-offset-2",
        "focus-visible:ring-offset-bg-canvas",
        className,
      ]
        .filter(Boolean)
        .join(" ")}
    >
      <EduConnectLogo {...logoProps} decorative />
    </Link>
  );
}
