"use client";

import {
  Badge,
  Button,
  buttonClasses,
  Card,
  CardContent,
  CardHeader,
  cn,
} from "@educonnect/ui";
import {
  BadgeCheck,
  Bookmark,
  BookmarkCheck,
  CircleSlash,
  DollarSign,
  ExternalLink,
  ListChecks,
  Lock,
  ScrollText,
  Sparkles,
  TriangleAlert,
  Wrench,
  type LucideIcon,
} from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import type { Tool } from "@/lib/api/tools";

/**
 * One curated tool.
 *
 * Curation transparency is a product invariant, not decoration: provenance,
 * cost, privacy and limitations are always rendered, never collapsed behind a
 * disclosure and never dropped on small screens.
 *
 * `matchReason` is AI-derived when the scenario ranker answered. It is
 * interpolated as a plain React child, so React escapes it - there is no
 * `dangerouslySetInnerHTML` anywhere on this surface. `tool-card.test.tsx`
 * proves an injection payload renders as inert text.
 */
export type ToolCardProps = {
  tool: Tool;
  /** Present only in scenario-search results. */
  matchReason?: string;
  /** False when the deterministic ranker answered, so the caption stays honest. */
  aiRanked?: boolean;
  /** 1-based rank, shown only when the list is genuinely ordered by relevance. */
  rank?: number;
  busy: boolean;
  onToggleSave: () => void;
  onToggleDismiss: () => void;
  className?: string;
};

const REVIEWED_FORMAT = new Intl.DateTimeFormat("en-US", {
  month: "short",
  day: "numeric",
  year: "numeric",
});

function NoteRow({
  icon: Icon,
  tone,
  label,
  children,
}: {
  icon: LucideIcon;
  tone: string;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex gap-2.5">
      <Icon aria-hidden="true" className={cn("mt-0.5 size-4 shrink-0", tone)} />
      <p className="text-body text-text-secondary">
        <span className="font-medium text-text-primary">{label}: </span>
        {children}
      </p>
    </div>
  );
}

export function ToolCard({
  tool,
  matchReason,
  aiRanked = false,
  rank,
  busy,
  onToggleSave,
  onToggleDismiss,
  className,
}: ToolCardProps) {
  const saved = tool.viewer_state.saved;
  const dismissed = tool.viewer_state.dismissed;

  return (
    <Card
      className={cn(
        "flex flex-col transition-colors hover:border-border-strong",
        dismissed ? "opacity-60" : null,
        className,
      )}
    >
      <CardHeader className="space-y-2.5">
        <div className="flex items-start gap-3">
          <IconChip icon={Wrench} accent="aiTools" size="lg" bordered />
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              {typeof rank === "number" ? (
                <span className="rounded-full bg-status-ai/12 px-2 py-0.5 text-caption font-semibold tabular-nums text-status-ai">
                  #{rank}
                </span>
              ) : null}
              <h3 className="text-h4 text-text-primary">{tool.name}</h3>
            </div>
            <p className="mt-1 text-body text-text-secondary">{tool.purpose}</p>
          </div>
          <Badge variant="ai">{tool.category.name}</Badge>
        </div>

        {matchReason !== undefined && matchReason !== "" ? (
          <div className="flex gap-2.5 rounded-md border border-status-ai/30 bg-status-ai/10 px-3 py-2.5">
            <Sparkles
              aria-hidden="true"
              className="mt-0.5 size-4 shrink-0 text-status-ai"
            />
            <p className="text-body text-text-secondary">
              <span className="font-medium text-text-primary">
                {aiRanked ? "Why this matches: " : "Matched on: "}
              </span>
              {matchReason}
            </p>
          </div>
        ) : null}
      </CardHeader>

      <CardContent className="flex flex-1 flex-col gap-3">
        <NoteRow
          icon={BadgeCheck}
          tone="text-status-success"
          label="Why it fits"
        >
          {tool.selection_reason}
        </NoteRow>

        {tool.use_cases.length > 0 ? (
          <div className="flex gap-2.5">
            <ListChecks
              aria-hidden="true"
              className="mt-0.5 size-4 shrink-0 text-status-info"
            />
            <div className="space-y-1.5">
              <p className="text-body font-medium text-text-primary">
                Good for
              </p>
              <ul className="flex flex-wrap gap-1.5">
                {tool.use_cases.map((useCase) => (
                  <li key={useCase}>
                    <Badge variant="neutral">{useCase}</Badge>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        ) : null}

        <p className="text-body text-text-secondary">{tool.usage_guidance}</p>

        <div className="space-y-2.5 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-3">
          <NoteRow
            icon={TriangleAlert}
            tone="text-status-warning"
            label="Limitations"
          >
            {tool.limitations}
          </NoteRow>
          <NoteRow icon={DollarSign} tone="text-status-success" label="Cost">
            {tool.cost_note}
          </NoteRow>
          <NoteRow icon={Lock} tone="text-status-info" label="Privacy">
            {tool.privacy_note}
          </NoteRow>
          <NoteRow
            icon={ScrollText}
            tone="text-status-research"
            label="Provenance"
          >
            {tool.provenance}
          </NoteRow>
          <p className="pl-6 text-caption text-text-muted">
            Reviewed{" "}
            <span className="tabular-nums">
              {REVIEWED_FORMAT.format(new Date(tool.last_reviewed_at))}
            </span>
          </p>
        </div>

        <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-border-subtle pt-3">
          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant={saved ? "secondary" : "ghost"}
              size="sm"
              disabled={busy}
              aria-pressed={saved}
              onClick={onToggleSave}
            >
              {saved ? (
                <BookmarkCheck aria-hidden="true" className="size-4" />
              ) : (
                <Bookmark aria-hidden="true" className="size-4" />
              )}
              {saved ? "Saved" : "Save"}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              disabled={busy}
              aria-pressed={dismissed}
              className={dismissed ? "text-text-muted" : undefined}
              onClick={onToggleDismiss}
            >
              <CircleSlash aria-hidden="true" className="size-4" />
              {dismissed ? "Dismissed" : "Dismiss"}
            </Button>
          </div>
          <a
            href={tool.url}
            target="_blank"
            rel="noopener noreferrer"
            className={buttonClasses({ variant: "secondary", size: "sm" })}
          >
            Open tool
            <ExternalLink aria-hidden="true" className="size-4" />
          </a>
        </div>
      </CardContent>
    </Card>
  );
}
