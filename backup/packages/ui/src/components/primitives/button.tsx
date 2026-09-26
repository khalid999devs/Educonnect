import type { ComponentProps } from "react";

import { cn } from "../../lib/cn";
import { Spinner } from "../feedback/spinner";

export type ButtonVariant = "primary" | "secondary" | "ghost" | "destructive";

export type ButtonSize = "sm" | "md" | "lg";

const VARIANTS: Record<ButtonVariant, string> = {
  primary:
    "bg-brand-primary text-white hover:bg-brand-primary-hover active:bg-brand-primary-hover",
  secondary:
    "border border-border-default bg-bg-surface text-text-primary hover:bg-bg-interactive",
  ghost: "text-text-secondary hover:bg-bg-interactive hover:text-text-primary",
  destructive: "bg-status-error text-white hover:opacity-90",
};

/* 44px default action height; 48px for marketing/onboarding; 36px only for
   dense administrative rows (doc 04 minimum target stays 44x44 elsewhere). */
const SIZES: Record<ButtonSize, string> = {
  sm: "h-9 px-3.5",
  md: "h-11 px-5",
  lg: "h-12 px-6",
};

export type ButtonStyleOptions = {
  variant?: ButtonVariant;
  size?: ButtonSize;
  fullWidth?: boolean;
  /** Glow is reserved for the page's primary CTA (doc 04). */
  glow?: boolean;
};

/**
 * Class builder shared with link-shaped actions so anchors can carry the
 * exact button treatment without duplicating tokens.
 */
export function buttonClasses(
  {
    variant = "primary",
    size = "md",
    fullWidth = false,
    glow = false,
  }: ButtonStyleOptions = {},
  className?: string,
): string {
  return cn(
    "inline-flex items-center justify-center gap-2 rounded-md text-button transition-colors",
    "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
    "disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50",
    VARIANTS[variant],
    SIZES[size],
    fullWidth && "w-full",
    glow && variant === "primary" && "shadow-glow-sm",
    className,
  );
}

export type ButtonProps = ComponentProps<"button"> &
  ButtonStyleOptions & {
    isLoading?: boolean;
    loadingLabel?: string;
  };

export function Button({
  variant = "primary",
  size = "md",
  fullWidth = false,
  glow = false,
  isLoading = false,
  loadingLabel = "Working",
  disabled,
  className,
  children,
  type = "button",
  ...buttonProps
}: ButtonProps) {
  return (
    <button
      {...buttonProps}
      type={type}
      disabled={disabled || isLoading}
      aria-busy={isLoading || undefined}
      className={buttonClasses({ variant, size, fullWidth, glow }, className)}
    >
      {isLoading ? (
        <>
          <Spinner size="sm" />
          <span className="sr-only">{loadingLabel}</span>
        </>
      ) : null}
      {children}
    </button>
  );
}
