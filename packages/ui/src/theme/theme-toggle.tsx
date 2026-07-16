"use client";

import { Monitor, Moon, Sun, type LucideIcon } from "lucide-react";
import { useEffect, useState } from "react";

import { cn } from "../lib/cn";
import { useTheme } from "./theme-provider";
import type { Theme } from "./theme-script";

const OPTIONS: ReadonlyArray<{
  value: Theme;
  label: string;
  icon: LucideIcon;
}> = [
  { value: "light", label: "Light theme", icon: Sun },
  { value: "dark", label: "Dark theme", icon: Moon },
  { value: "system", label: "System theme", icon: Monitor },
];

/**
 * Three-state theme selector. Renders in a neutral state until mounted so
 * server and client markup agree regardless of the persisted choice.
 */
export function ThemeToggle({ className }: { className?: string }) {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return (
    <div
      role="radiogroup"
      aria-label="Theme"
      className={cn(
        "inline-flex items-center gap-1 rounded-full border border-border-default bg-bg-surface p-1",
        className,
      )}
    >
      {OPTIONS.map(({ value, label, icon: Icon }) => {
        const selected = mounted && theme === value;

        return (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={selected}
            aria-label={label}
            title={label}
            onClick={() => setTheme(value)}
            className={cn(
              "inline-flex size-9 items-center justify-center rounded-full text-text-muted transition-colors",
              "hover:bg-bg-interactive hover:text-text-primary",
              "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
              selected && "bg-bg-interactive text-brand-primary",
            )}
          >
            <Icon aria-hidden="true" className="size-4" />
          </button>
        );
      })}
    </div>
  );
}
