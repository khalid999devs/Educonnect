"use client";

import { Search } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { useDemo, type DemoView } from "./demo-app";
import { DEMO_TEMPLATES, RECOMMENDED_TOOLS } from "./demo-data";

type SearchHit = {
  id: string;
  label: string;
  meta: string;
  view: DemoView;
};

/**
 * Working demo search: filters the live demo state (tasks, notes,
 * resources, tools, templates) and navigates to the owning view. ⌘K / Ctrl+K
 * focuses it - a real control, not a decorative input.
 */
export function DemoSearch() {
  const { state, dispatch } = useDemo();
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        inputRef.current?.focus();
      }
    };

    window.addEventListener("keydown", onKeyDown);

    return () => window.removeEventListener("keydown", onKeyDown);
  }, []);

  const needle = query.trim().toLowerCase();
  const hits: SearchHit[] =
    needle.length < 2
      ? []
      : [
          ...state.tasks.map((task) => ({
            id: `task-${task.id}`,
            label: task.title,
            meta: `Task · ${task.courseCode} · due ${task.due}`,
            view: "planner" as const,
          })),
          ...state.notes.map((note) => ({
            id: `note-${note.id}`,
            label: note.title,
            meta: `Second Brain · ${note.courseCode}`,
            view: "brain" as const,
          })),
          ...state.resources.map((resource) => ({
            id: `res-${resource.id}`,
            label: resource.title,
            meta: `Resource · ${resource.courseCode}`,
            view: "resources" as const,
          })),
          ...RECOMMENDED_TOOLS.map((tool) => ({
            id: `tool-${tool.id}`,
            label: tool.name,
            meta: "AI Tools · sample entry",
            view: "tools" as const,
          })),
          ...DEMO_TEMPLATES.map((template) => ({
            id: `tpl-${template.id}`,
            label: template.name,
            meta: "Template · approved free",
            view: "templates" as const,
          })),
        ]
          .filter((hit) => hit.label.toLowerCase().includes(needle))
          .slice(0, 7);

  return (
    <div className="relative w-full max-w-sm">
      <Search
        aria-hidden="true"
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-muted"
      />
      <input
        ref={inputRef}
        type="search"
        value={query}
        aria-label="Search the demo workspace"
        placeholder="Search anything…"
        onChange={(event) => {
          setQuery(event.target.value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
        onKeyDown={(event) => {
          if (event.key === "Escape") {
            setQuery("");
            setOpen(false);
          }
        }}
        className="h-10 w-full rounded-md border border-border-default bg-bg-canvas pl-9 pr-12 text-body text-text-primary placeholder:text-text-muted focus-visible:border-brand-focus focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand-focus"
      />
      <kbd
        aria-hidden="true"
        className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded-sm border border-border-default bg-bg-subtle px-1.5 py-0.5 text-caption text-text-muted"
      >
        ⌘K
      </kbd>

      {open && hits.length > 0 ? (
        <ul className="absolute left-0 right-0 top-11 z-30 overflow-hidden rounded-md border border-border-default bg-bg-elevated shadow-glow-sm">
          {hits.map((hit) => (
            <li key={hit.id}>
              <button
                type="button"
                onMouseDown={(event) => {
                  event.preventDefault();
                  dispatch({ type: "navigate", view: hit.view });
                  setQuery("");
                  setOpen(false);
                }}
                className="flex w-full flex-col gap-0.5 px-3.5 py-2.5 text-left hover:bg-bg-interactive focus-visible:bg-bg-interactive focus-visible:outline-none"
              >
                <span className="truncate text-body text-text-primary">
                  {hit.label}
                </span>
                <span className="text-caption text-text-muted">{hit.meta}</span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      {open && needle.length >= 2 && hits.length === 0 ? (
        <p className="absolute left-0 right-0 top-11 z-30 rounded-md border border-border-default bg-bg-elevated px-3.5 py-2.5 text-caption text-text-muted">
          Nothing in the demo matches "{query.trim()}".
        </p>
      ) : null}
    </div>
  );
}
