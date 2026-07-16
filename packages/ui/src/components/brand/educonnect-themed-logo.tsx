import { cn } from "../../lib/cn";
import { EduConnectLogo, type EduConnectLogoProps } from "./educonnect-logo";

export type EduConnectThemedLogoProps = Omit<
  EduConnectLogoProps,
  "theme" | "variant"
> & {
  className?: string;
};

/*
 * Measured alpha bounds of the horizontal logo assets (866x288): the artwork
 * starts 13.2% in from the left and ends 9.5% short of the right. The raw
 * render is scaled up so `width` means the VISIBLE artwork width, and the
 * transparent padding is cropped with negative margins — the source PNGs
 * stay untouched and the logo optically aligns at its intended size.
 */
const TRIM_LEFT = 0.132;
const TRIM_RIGHT = 0.095;
const VISIBLE_RATIO = 1 - TRIM_LEFT - TRIM_RIGHT;

/**
 * Renders the light-surface logo in light theme and the dark-surface logo in
 * dark theme using the `.dark` class, so shells stay correct across the
 * pre-hydration theme script and later toggles. Horizontal variant only;
 * `width` is the visible artwork width in pixels.
 */
export function EduConnectThemedLogo({
  className,
  width = 150,
  ...logoProps
}: EduConnectThemedLogoProps) {
  const rawWidth = Math.round(width / VISIBLE_RATIO);
  const trim = {
    marginLeft: `${-(rawWidth * TRIM_LEFT).toFixed(1)}px`,
    marginRight: `${-(rawWidth * TRIM_RIGHT).toFixed(1)}px`,
  };

  return (
    <>
      <span className={cn("dark:hidden", className)}>
        <EduConnectLogo
          {...logoProps}
          width={rawWidth}
          style={trim}
          theme="light"
        />
      </span>
      <span className={cn("hidden dark:inline", className)}>
        <EduConnectLogo
          {...logoProps}
          width={rawWidth}
          style={trim}
          theme="dark"
        />
      </span>
    </>
  );
}
