import { cn } from "../../lib/cn";
import { EduConnectLogo, type EduConnectLogoProps } from "./educonnect-logo";

export type EduConnectThemedLogoProps = Omit<EduConnectLogoProps, "theme"> & {
  className?: string;
};

/**
 * Renders the light-surface logo in light theme and the dark-surface logo in
 * dark theme using the `.dark` class, so shells stay correct across the
 * pre-hydration theme script and later toggles.
 */
export function EduConnectThemedLogo({
  className,
  ...logoProps
}: EduConnectThemedLogoProps) {
  return (
    <>
      <span className={cn("dark:hidden", className)}>
        <EduConnectLogo {...logoProps} theme="light" />
      </span>
      <span className={cn("hidden dark:inline", className)}>
        <EduConnectLogo {...logoProps} theme="dark" />
      </span>
    </>
  );
}
