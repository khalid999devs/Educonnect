"use client";

import { Select, Spinner } from "@educonnect/ui";
import { Sparkles } from "lucide-react";

import { SearchBar } from "@/components/shared/search-bar";
import type { ToolPreference, ToolSort } from "@/lib/api/tools";

/**
 * The full-width smart search that sits IN the page cover (PageCover `search`
 * slot), not a small field tucked into a card header.
 *
 * It takes the student's own words - "I have an exam in 3 days" - and never
 * blocks typing: the input is fully controlled by `value`, the caller debounces
 * before it calls the ranker, and `pending` only ever renders an inline
 * spinner. The results below stay on screen while a newer ranking is in flight.
 *
 * Contrast over the photo band: the search field uses `SearchBar tone="overlay"`
 * (`bg-bg-surface/85 backdrop-blur-sm`) and the two `Select`s are already
 * opaque `bg-bg-surface`, so every control clears 4.5:1 in both themes without
 * darkening the cover gradient.
 */
export type SmartSearchProps = {
  value: string;
  onChange: (value: string) => void;
  pending: boolean;
  /** True once the query is long enough that the ranker is driving results. */
  active: boolean;
  preference: ToolPreference;
  onPreferenceChange: (value: ToolPreference) => void;
  sort: ToolSort;
  onSortChange: (value: ToolSort) => void;
};

export function SmartSearch({
  value,
  onChange,
  pending,
  active,
  preference,
  onPreferenceChange,
  sort,
  onSortChange,
}: SmartSearchProps) {
  return (
    <div className="space-y-2">
      <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
        <div className="relative flex-1">
          <SearchBar
            value={value}
            onChange={onChange}
            size="lg"
            tone="overlay"
            label="Describe your situation to rank the tool catalog"
            placeholder="Describe your situation, e.g. I have an exam in 3 days…"
          />
          {pending ? (
            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-status-ai">
              <Spinner size="sm" label="Ranking tools" />
            </span>
          ) : null}
        </div>

        <div className="flex flex-wrap gap-2">
          <Select
            value={preference}
            aria-label="Filter tools by your saved and dismissed choices"
            onChange={(event) =>
              onPreferenceChange(event.target.value as ToolPreference)
            }
            className="w-44"
          >
            <option value="all">Everything</option>
            <option value="saved">Saved</option>
            <option value="none">Not yet decided</option>
            <option value="dismissed">Dismissed</option>
          </Select>

          <Select
            value={sort}
            aria-label="Sort the tool catalog"
            disabled={active}
            onChange={(event) => onSortChange(event.target.value as ToolSort)}
            className="w-48"
          >
            <option value="name">Name A to Z</option>
            <option value="-last_reviewed_at">Recently reviewed</option>
          </Select>
        </div>
      </div>

      <p
        aria-live="polite"
        className="flex items-center gap-1.5 text-caption text-text-secondary"
      >
        <Sparkles aria-hidden="true" className="size-3.5 text-status-ai" />
        {active
          ? pending
            ? "Ranking the curated catalog against your situation…"
            : "Ranked against your situation. Ranking only reorders the curated catalog, it never invents a tool."
          : "Type any situation in your own words. Leave it empty to browse what we recommend."}
      </p>
    </div>
  );
}
