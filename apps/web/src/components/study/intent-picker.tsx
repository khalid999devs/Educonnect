"use client";

import { Button, cn } from "@educonnect/ui";
import { Check } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";

import {
  findStudyIntent,
  STUDY_INTENTS,
  type StudyIntent,
} from "./study-intents";

/**
 * Step one, rendered inline on entry. Three options, no dialog: choosing one
 * collapses the picker into a single summary row that can be reopened in
 * place, so the student never loses the page they were on.
 */
export function IntentPicker({
  value,
  onChange,
  onReopen,
  expanded,
}: {
  value: StudyIntent | null;
  onChange: (intent: StudyIntent) => void;
  onReopen: () => void;
  expanded: boolean;
}) {
  if (value !== null && !expanded) {
    const chosen = findStudyIntent(value);

    return (
      <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border-default bg-bg-surface p-3">
        <IconChip icon={chosen.icon} accent="study" size="sm" />
        <div className="min-w-0 flex-1">
          <p className="text-label text-text-primary">{chosen.label}</p>
          <p className="truncate text-caption text-text-muted">
            {chosen.tagline}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={onReopen}>
          Change
        </Button>
      </div>
    );
  }

  return (
    <div
      role="radiogroup"
      aria-label="What are you here to do?"
      className="grid gap-3 md:grid-cols-3"
    >
      {STUDY_INTENTS.map((intent, index) => {
        const selected = intent.value === value;

        return (
          <button
            key={intent.value}
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={() => onChange(intent.value)}
            className={cn(
              "group flex h-full flex-col items-start gap-3 rounded-lg border bg-bg-surface p-4 text-left transition-colors",
              "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
              "motion-safe:animate-fade-up",
              selected
                ? "border-status-ai/40 shadow-glow-sm"
                : "border-border-default hover:border-border-strong",
              index === 1 ? "motion-safe:[animation-delay:80ms]" : null,
              index === 2 ? "motion-safe:[animation-delay:160ms]" : null,
            )}
          >
            <div className="flex w-full items-start justify-between gap-2">
              <IconChip icon={intent.icon} accent="study" size="md" />
              {selected ? (
                <Check aria-hidden="true" className="size-5 text-status-ai" />
              ) : null}
            </div>
            <div className="space-y-1">
              <p className="text-h4 text-text-primary">{intent.label}</p>
              <p className="text-body text-text-secondary">
                {intent.description}
              </p>
            </div>
          </button>
        );
      })}
    </div>
  );
}
