"use client";

import { EmptyState, ErrorState, Skeleton, cn } from "@educonnect/ui";
import { Compass, Sparkles, Target } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import type { ToolCategory } from "@/lib/api/tools";

/**
 * The goal dimension, straight from `GET /tool-categories` - the whole curated
 * list, ordered by sort_order then name. The previous surface unioned the
 * first page of three separate listings and silently lost every category that
 * appeared only past page one.
 *
 * "Recommended" is the default: no goal selected means the full recommended
 * catalog. Selecting a goal narrows tools, prompts and workflows together.
 */
export type GoalChipsProps = {
  categories: ToolCategory[];
  value: string | null;
  onChange: (value: string | null) => void;
  isPending: boolean;
  isError: boolean;
  onRetry: () => void;
};

function chipClass(active: boolean): string {
  return cn(
    "rounded-full border px-3.5 py-1.5 text-body transition-colors",
    "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
    active
      ? "border-status-ai/40 bg-status-ai/12 text-text-primary"
      : "border-border-default bg-bg-surface text-text-secondary hover:border-border-strong hover:text-text-primary",
  );
}

export function GoalChips({
  categories,
  value,
  onChange,
  isPending,
  isError,
  onRetry,
}: GoalChipsProps) {
  const selected = categories.find((category) => category.key === value);

  return (
    <section className="space-y-3 rounded-xl border border-border-default bg-bg-surface p-5 motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]">
      <div className="flex items-center gap-3">
        <IconChip icon={Target} accent="aiTools" size="md" />
        <div>
          <h2 className="text-h4 text-text-primary">Start from a goal</h2>
          <p className="text-caption text-text-muted">
            Recommended is everything we curate. Pick a goal to narrow it.
          </p>
        </div>
      </div>

      {isPending ? (
        <div className="flex flex-wrap gap-2">
          <Skeleton className="h-9 w-32 rounded-full" />
          <Skeleton className="h-9 w-40 rounded-full" />
          <Skeleton className="h-9 w-28 rounded-full" />
          <Skeleton className="h-9 w-36 rounded-full" />
        </div>
      ) : isError ? (
        <ErrorState title="Goals could not load" onRetry={onRetry} />
      ) : categories.length === 0 ? (
        <EmptyState
          icon={Compass}
          title="Goals are being curated"
          description="Reviewed goal categories will appear here as our team publishes them. Nothing is auto-generated."
        />
      ) : (
        <>
          <div
            role="group"
            aria-label="Filter by goal"
            className="flex flex-wrap gap-2"
          >
            <button
              type="button"
              aria-pressed={value === null}
              onClick={() => onChange(null)}
              className={chipClass(value === null)}
            >
              <span className="flex items-center gap-1.5">
                <Sparkles
                  aria-hidden="true"
                  className="size-4 text-status-ai"
                />
                Recommended
              </span>
            </button>
            {categories.map((category) => (
              <button
                key={category.key}
                type="button"
                aria-pressed={value === category.key}
                onClick={() =>
                  onChange(value === category.key ? null : category.key)
                }
                className={chipClass(value === category.key)}
              >
                {category.name}
              </button>
            ))}
          </div>

          {selected?.description ? (
            <p className="text-body text-text-secondary">
              {selected.description}
            </p>
          ) : null}
        </>
      )}
    </section>
  );
}
