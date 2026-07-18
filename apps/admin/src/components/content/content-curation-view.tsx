"use client";

import Link from "next/link";

import type { ContentType } from "@/lib/api/admin-content";

import { PromptsCuration } from "./prompts-curation";
import { ToolsCuration } from "./tools-curation";
import { WorkflowsCuration } from "./workflows-curation";

const TABS: Array<{ type: ContentType; label: string }> = [
  { type: "tools", label: "Tools" },
  { type: "prompts", label: "Prompts" },
  { type: "workflows", label: "Workflows" },
];

const DESCRIPTION: Record<ContentType, string> = {
  tools: "Curated tools students can reach from goal-based guidance.",
  prompts: "Reusable prompt templates with integrity notes and related tools.",
  workflows: "Step-by-step recipes that move a goal to a reviewed result.",
};

export function ContentCurationView({ type }: { type: ContentType }) {
  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Content curation</h1>
        <p className="text-body text-text-secondary">{DESCRIPTION[type]}</p>
      </header>

      <nav className="flex gap-1 border-b border-border-subtle">
        {TABS.map((tab) => (
          <Link
            key={tab.type}
            href={`/content/${tab.type}`}
            aria-current={tab.type === type ? "page" : undefined}
            className={
              tab.type === type
                ? "border-b-2 border-brand-primary px-4 py-2 text-body font-medium text-text-primary"
                : "border-b-2 border-transparent px-4 py-2 text-body text-text-muted hover:text-text-primary"
            }
          >
            {tab.label}
          </Link>
        ))}
      </nav>

      {type === "tools" ? <ToolsCuration /> : null}
      {type === "prompts" ? <PromptsCuration /> : null}
      {type === "workflows" ? <WorkflowsCuration /> : null}
    </div>
  );
}
