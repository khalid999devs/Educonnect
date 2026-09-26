import Image, { type ImageProps, type StaticImageData } from "next/image";

import fullLogoBlackWhite from "../../assets/brand/full_logo_b_w.png";
import fullLogoDark from "../../assets/brand/full_logo_dark.png";
import fullLogoLight from "../../assets/brand/full_logo_light.png";
import fullLogoVerticalDark from "../../assets/brand/full_logo_v_dark.png";
import fullLogoVerticalLight from "../../assets/brand/full_logo_v_light.png";
import iconLogo from "../../assets/brand/icon_logo.png";

export type EduConnectLogoTheme = "dark" | "light";

export type EduConnectLogoVariant =
  "horizontal" | "vertical" | "icon" | "monochrome";

type LogoAssetMap = Record<
  Exclude<EduConnectLogoVariant, "icon" | "monochrome">,
  Record<EduConnectLogoTheme, StaticImageData>
>;

const logoAssets: LogoAssetMap = {
  horizontal: {
    dark: fullLogoDark,
    light: fullLogoLight,
  },
  vertical: {
    dark: fullLogoVerticalDark,
    light: fullLogoVerticalLight,
  },
};

export type EduConnectLogoProps = Omit<
  ImageProps,
  "src" | "alt" | "width" | "height"
> & {
  /**
   * The background/theme where the logo will appear.
   *
   * "dark" chooses the logo prepared for dark surfaces.
   * "light" chooses the logo prepared for light surfaces.
   */
  theme?: EduConnectLogoTheme;

  /**
   * horizontal: mark and wordmark side by side
   * vertical: mark above wordmark
   * icon: mark only
   * monochrome: black/white full logo
   */
  variant?: EduConnectLogoVariant;

  /**
   * Rendered width for horizontal, vertical, and monochrome logos.
   * The height is calculated automatically from the source ratio.
   */
  width?: number;

  /**
   * Square size used only by the icon variant.
   */
  iconSize?: number;

  /**
   * Set true when adjacent text already identifies EduConnect.
   */
  decorative?: boolean;

  /**
   * Accessible label when the logo is not decorative.
   */
  alt?: string;
};

function getLogoSource(
  variant: EduConnectLogoVariant,
  theme: EduConnectLogoTheme,
): StaticImageData {
  if (variant === "icon") {
    return iconLogo;
  }

  if (variant === "monochrome") {
    return fullLogoBlackWhite;
  }

  return logoAssets[variant][theme];
}

export function EduConnectLogo({
  theme = "dark",
  variant = "horizontal",
  width,
  iconSize = 40,
  decorative = false,
  alt = "EduConnect",
  className,
  priority = false,
  ...imageProps
}: EduConnectLogoProps) {
  const source = getLogoSource(variant, theme);

  const renderedWidth =
    variant === "icon"
      ? iconSize
      : (width ?? (variant === "vertical" ? 180 : 190));

  const renderedHeight =
    variant === "icon"
      ? iconSize
      : Math.round(renderedWidth * (source.height / source.width));

  return (
    <Image
      {...imageProps}
      src={source}
      alt={decorative ? "" : alt}
      width={renderedWidth}
      height={renderedHeight}
      priority={priority}
      className={["block h-auto max-w-full object-contain", className]
        .filter(Boolean)
        .join(" ")}
    />
  );
}
