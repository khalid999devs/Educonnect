import { CircleAlert } from "lucide-react";
import { useId, type ReactNode } from "react";

import { cn } from "../../lib/cn";
import { Label } from "./label";

export type FormFieldControlProps = {
  id: string;
  required: boolean;
  "aria-describedby": string | undefined;
  "aria-invalid": true | undefined;
};

export type FormFieldProps = {
  label: string;
  /** Renders the control with the wiring props (id, describedby, invalid). */
  children: (control: FormFieldControlProps) => ReactNode;
  id?: string;
  hint?: string;
  error?: string;
  required?: boolean;
  className?: string;
};

/**
 * Field wrapper that owns label association, hint/error descriptions, and
 * invalid state so every form in web and admin gets identical accessible
 * wiring. Backend validation remains authoritative; this only presents it.
 */
export function FormField({
  label,
  children,
  id,
  hint,
  error,
  required = false,
  className,
}: FormFieldProps) {
  const generatedId = useId();
  const fieldId = id ?? generatedId;
  const hintId = hint ? `${fieldId}-hint` : undefined;
  const errorId = error ? `${fieldId}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div className={cn("space-y-1.5", className)}>
      <Label htmlFor={fieldId} required={required}>
        {label}
      </Label>
      {children({
        id: fieldId,
        required,
        "aria-describedby": describedBy,
        "aria-invalid": error ? true : undefined,
      })}
      {hint ? (
        <p id={hintId} className="text-caption text-text-muted">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p
          id={errorId}
          className="flex items-center gap-1 text-caption text-status-error"
        >
          <CircleAlert aria-hidden="true" className="size-3.5 shrink-0" />
          {error}
        </p>
      ) : null}
    </div>
  );
}
