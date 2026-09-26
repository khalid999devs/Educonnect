"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  Textarea,
} from "@educonnect/ui";
import {
  BadgeCheck,
  BookmarkCheck,
  Bookmark,
  CircleSlash,
  Copy,
  ExternalLink,
  type LucideIcon,
  ShieldQuestion,
  Sparkles,
} from "lucide-react";
import { useState } from "react";

import type { Prompt, Workflow } from "@/lib/api/guidance";

const DESTINATION_LABELS: Record<string, string> = {
  create_task: "Create a task",
  save_resource: "Save to resources",
  use_tool: "Use a tool",
  use_prompt: "Use a prompt",
  use_template: "Use a template",
};

/** Small labelled paragraph used for the tool's rationale/limit/cost notes. */
function NoteRow({
  icon: Icon,
  label,
  children,
  tone = "muted",
}: {
  icon: LucideIcon;
  label: string;
  children: React.ReactNode;
  tone?: "muted" | "warning";
}) {
  return (
    <div className="flex gap-2.5">
      <Icon
        aria-hidden="true"
        className={cn(
          "mt-0.5 size-4 shrink-0",
          tone === "warning" ? "text-status-deadline" : "text-text-muted",
        )}
      />
      <p className="text-body text-text-secondary">
        <span className="font-medium text-text-primary">{label}: </span>
        {children}
      </p>
    </div>
  );
}

function SaveDismissRow({
  saved,
  dismissed,
  busy,
  onToggleSave,
  onToggleDismiss,
}: {
  saved: boolean;
  dismissed: boolean;
  busy: boolean;
  onToggleSave: () => void;
  onToggleDismiss: () => void;
}) {
  return (
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
  );
}

export function PromptCard({
  prompt,
  busy,
  onToggleSave,
  onToggleDismiss,
  onCopy,
}: {
  prompt: Prompt;
  busy: boolean;
  onToggleSave: () => void;
  onToggleDismiss: () => void;
  onCopy: (filledBody: string) => void;
}) {
  const [body, setBody] = useState(prompt.template_body);
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(body);
    } catch {
      /* Clipboard may be unavailable; still record intent and show the body. */
    }

    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
    onCopy(body);
  };

  return (
    <Card className={prompt.viewer_state.dismissed ? "opacity-60" : undefined}>
      <CardHeader className="space-y-2">
        <div className="flex items-start justify-between gap-3">
          <CardTitle>{prompt.title}</CardTitle>
          <Badge variant="brand">{prompt.category.name}</Badge>
        </div>
        <p className="text-body text-text-secondary">{prompt.purpose}</p>
      </CardHeader>
      <CardContent className="space-y-3">
        {prompt.placeholders.length > 0 ? (
          <div className="space-y-1">
            <p className="text-caption font-medium text-text-muted">
              Fill in before you use it
            </p>
            <ul className="flex flex-wrap gap-1.5">
              {prompt.placeholders.map((placeholder) => (
                <li key={placeholder}>
                  <Badge variant="neutral">{placeholder}</Badge>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <label className="block space-y-1">
          <span className="text-caption font-medium text-text-muted">
            Editable prompt
          </span>
          <Textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            className="min-h-36 font-mono text-caption"
            aria-label={`Editable prompt for ${prompt.title}`}
          />
        </label>

        <NoteRow icon={Sparkles} label="Expected output">
          {prompt.expected_output}
        </NoteRow>
        <NoteRow
          icon={ShieldQuestion}
          label="Academic integrity"
          tone="warning"
        >
          {prompt.integrity_note}
        </NoteRow>

        {prompt.related_tools.length > 0 ? (
          <div className="space-y-1">
            <p className="text-caption font-medium text-text-muted">
              Works well with
            </p>
            <div className="flex flex-wrap gap-1.5">
              {prompt.related_tools.map((related) => (
                <a
                  key={related.id}
                  href={related.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1 rounded-full border border-border-default px-2.5 py-1 text-caption text-text-secondary hover:border-border-strong"
                >
                  {related.name}
                  <ExternalLink aria-hidden="true" className="size-3" />
                </a>
              ))}
            </div>
          </div>
        ) : null}

        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border-subtle pt-3">
          <SaveDismissRow
            saved={prompt.viewer_state.saved}
            dismissed={prompt.viewer_state.dismissed}
            busy={busy}
            onToggleSave={onToggleSave}
            onToggleDismiss={onToggleDismiss}
          />
          <div className="flex items-center gap-2">
            {prompt.viewer_state.copy_count > 0 ? (
              <span className="text-caption text-text-muted">
                Copied {prompt.viewer_state.copy_count}×
              </span>
            ) : null}
            <Button variant="primary" size="sm" onClick={copy}>
              <Copy aria-hidden="true" className="size-4" />
              {copied ? "Copied" : "Copy prompt"}
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export function WorkflowCard({
  workflow,
  busy,
  onToggleSave,
  onToggleDismiss,
}: {
  workflow: Workflow;
  busy: boolean;
  onToggleSave: () => void;
  onToggleDismiss: () => void;
}) {
  return (
    <Card
      className={workflow.viewer_state.dismissed ? "opacity-60" : undefined}
    >
      <CardHeader className="space-y-2">
        <div className="flex items-start justify-between gap-3">
          <CardTitle>{workflow.title}</CardTitle>
          <Badge variant="research">{workflow.category.name}</Badge>
        </div>
        <p className="text-body text-text-secondary">{workflow.goal}</p>
      </CardHeader>
      <CardContent className="space-y-3">
        <NoteRow icon={BadgeCheck} label="You'll end up with">
          {workflow.expected_outcome}
        </NoteRow>

        <ol className="space-y-2.5">
          {workflow.steps.map((step) => (
            <li key={step.number} className="flex gap-3">
              <span
                aria-hidden="true"
                className="flex size-6 shrink-0 items-center justify-center rounded-full bg-bg-interactive text-caption font-semibold text-brand-primary"
              >
                {step.number}
              </span>
              <div className="space-y-1">
                <p className="text-body font-medium text-text-primary">
                  {step.title}
                </p>
                <p className="text-body text-text-secondary">
                  {step.instruction}
                </p>
                <div className="flex flex-wrap gap-1.5">
                  {step.destination_action ? (
                    <Badge variant="info">
                      {DESTINATION_LABELS[step.destination_action] ??
                        step.destination_action}
                    </Badge>
                  ) : null}
                  {step.tool ? (
                    <a
                      href={step.tool.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1 rounded-full border border-border-default px-2 py-0.5 text-caption text-text-secondary hover:border-border-strong"
                    >
                      {step.tool.name}
                      <ExternalLink aria-hidden="true" className="size-3" />
                    </a>
                  ) : null}
                  {step.prompt ? (
                    <Badge variant="neutral">Prompt: {step.prompt.title}</Badge>
                  ) : null}
                  {step.template ? (
                    <Badge variant="neutral">
                      Template: {step.template.title}
                    </Badge>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ol>

        <NoteRow
          icon={ShieldQuestion}
          label="Academic integrity"
          tone="warning"
        >
          {workflow.integrity_note}
        </NoteRow>

        <div className="border-t border-border-subtle pt-3">
          <SaveDismissRow
            saved={workflow.viewer_state.saved}
            dismissed={workflow.viewer_state.dismissed}
            busy={busy}
            onToggleSave={onToggleSave}
            onToggleDismiss={onToggleDismiss}
          />
        </div>
      </CardContent>
    </Card>
  );
}
