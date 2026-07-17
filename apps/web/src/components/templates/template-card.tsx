"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
} from "@educonnect/ui";
import {
  Bookmark,
  BookmarkCheck,
  CircleSlash,
  Eye,
  FilePlus2,
  LayoutTemplate,
  ShieldQuestion,
} from "lucide-react";

import type { Template } from "@/lib/api/templates";

export function TemplateCard({
  template,
  busy,
  onPreview,
  onUse,
  onToggleSave,
  onToggleDismiss,
}: {
  template: Template;
  busy: boolean;
  onPreview: () => void;
  onUse: () => void;
  onToggleSave: () => void;
  onToggleDismiss: () => void;
}) {
  const { viewer_state: viewer } = template;

  return (
    <Card
      className={cn(
        "flex flex-col",
        viewer.dismissed ? "opacity-60" : undefined,
      )}
    >
      <CardHeader className="space-y-2">
        <div className="flex items-start justify-between gap-2">
          <span className="flex size-10 items-center justify-center rounded-lg bg-bg-interactive">
            <LayoutTemplate
              aria-hidden="true"
              className="size-5 text-status-info"
            />
          </span>
          <Badge variant="success">Approved · Free</Badge>
        </div>
        <CardTitle className="text-h4">{template.title}</CardTitle>
        <Badge variant="neutral">{template.category.name}</Badge>
      </CardHeader>
      <CardContent className="flex flex-1 flex-col gap-3">
        <p className="text-body text-text-secondary">{template.summary}</p>

        <p className="flex gap-2 text-caption text-text-muted">
          <ShieldQuestion
            aria-hidden="true"
            className="mt-0.5 size-3.5 shrink-0 text-status-deadline"
          />
          {template.integrity_note}
        </p>

        {viewer.active_copy_count > 0 ? (
          <p className="text-caption text-text-muted">
            You have {viewer.active_copy_count} active{" "}
            {viewer.active_copy_count === 1 ? "copy" : "copies"} in your
            library.
          </p>
        ) : null}

        <div className="mt-auto flex flex-wrap items-center gap-2 border-t border-border-subtle pt-3">
          <Button variant="secondary" size="sm" onClick={onPreview}>
            <Eye aria-hidden="true" className="size-4" />
            Preview
          </Button>
          <Button
            variant="primary"
            size="sm"
            onClick={onUse}
            disabled={template.latest_version === null}
          >
            <FilePlus2 aria-hidden="true" className="size-4" />
            Use template
          </Button>
          <div className="ml-auto flex items-center gap-1">
            <Button
              variant="ghost"
              size="sm"
              disabled={busy}
              aria-pressed={viewer.saved}
              aria-label={viewer.saved ? "Unsave template" : "Save template"}
              onClick={onToggleSave}
            >
              {viewer.saved ? (
                <BookmarkCheck aria-hidden="true" className="size-4" />
              ) : (
                <Bookmark aria-hidden="true" className="size-4" />
              )}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              disabled={busy}
              aria-pressed={viewer.dismissed}
              aria-label={
                viewer.dismissed ? "Undismiss template" : "Dismiss template"
              }
              className={viewer.dismissed ? "text-text-muted" : undefined}
              onClick={onToggleDismiss}
            >
              <CircleSlash aria-hidden="true" className="size-4" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
