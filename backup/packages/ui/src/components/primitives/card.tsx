import type { ComponentProps, ElementType, ReactNode } from "react";

import { cn } from "../../lib/cn";

/** Standard card: 16px radius, 24px padding, semantic border (doc 04). */
export function Card({ className, ...divProps }: ComponentProps<"div">) {
  return (
    <div
      {...divProps}
      className={cn(
        "rounded-lg border border-border-default bg-bg-surface p-6",
        className,
      )}
    />
  );
}

export function CardHeader({ className, ...divProps }: ComponentProps<"div">) {
  return <div {...divProps} className={cn("mb-4 space-y-1", className)} />;
}

export function CardTitle({
  as: Heading = "h3",
  className,
  children,
}: {
  as?: ElementType;
  className?: string;
  children: ReactNode;
}) {
  return (
    <Heading className={cn("text-h4 text-text-primary", className)}>
      {children}
    </Heading>
  );
}

export function CardDescription({
  className,
  ...paragraphProps
}: ComponentProps<"p">) {
  return (
    <p
      {...paragraphProps}
      className={cn("text-body text-text-secondary", className)}
    />
  );
}

export function CardContent({ className, ...divProps }: ComponentProps<"div">) {
  return (
    <div
      {...divProps}
      className={cn("text-body text-text-secondary", className)}
    />
  );
}

export function CardFooter({ className, ...divProps }: ComponentProps<"div">) {
  return (
    <div
      {...divProps}
      className={cn("mt-5 flex flex-wrap items-center gap-3", className)}
    />
  );
}
