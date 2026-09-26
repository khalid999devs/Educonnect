import {
  CircleAlert,
  CircleCheck,
  Info,
  Sparkles,
  TriangleAlert,
  type LucideIcon,
} from "lucide-react";
import type { ReactNode } from "react";

import { cn } from "../../lib/cn";

export type AlertVariant = "info" | "success" | "warning" | "error" | "ai";

const VARIANTS: Record<
  AlertVariant,
  { icon: LucideIcon; classes: string; iconClasses: string; role: string }
> = {
  info: {
    icon: Info,
    classes: "border-status-info/30 bg-status-info/10",
    iconClasses: "text-status-info",
    role: "status",
  },
  success: {
    icon: CircleCheck,
    classes: "border-status-success/30 bg-status-success/10",
    iconClasses: "text-status-success",
    role: "status",
  },
  warning: {
    icon: TriangleAlert,
    classes: "border-status-warning/40 bg-status-warning/10",
    iconClasses: "text-status-warning",
    role: "alert",
  },
  error: {
    icon: CircleAlert,
    classes: "border-status-error/30 bg-status-error/10",
    iconClasses: "text-status-error",
    role: "alert",
  },
  ai: {
    icon: Sparkles,
    classes: "border-status-ai/30 bg-status-ai/10",
    iconClasses: "text-status-ai",
    role: "status",
  },
};

export type AlertProps = {
  variant?: AlertVariant;
  title: string;
  children?: ReactNode;
  className?: string;
};

export function Alert({
  variant = "info",
  title,
  children,
  className,
}: AlertProps) {
  const { icon: Icon, classes, iconClasses, role } = VARIANTS[variant];

  return (
    <div
      role={role}
      className={cn("flex gap-3 rounded-md border p-4", classes, className)}
    >
      <Icon
        aria-hidden="true"
        className={cn("mt-0.5 size-5 shrink-0", iconClasses)}
      />
      <div className="space-y-1">
        <p className="text-body font-semibold text-text-primary">{title}</p>
        {children ? (
          <div className="text-body text-text-secondary">{children}</div>
        ) : null}
      </div>
    </div>
  );
}
