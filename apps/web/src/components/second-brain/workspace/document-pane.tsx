"use client";

import {
  Alert,
  Badge,
  Button,
  cn,
  EmptyState,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import { ExternalLink, FileText, Link2, ScrollText } from "lucide-react";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import {
  getIntakeExtraction,
  MAX_EXTRACTION_WINDOW_CHARACTERS,
} from "@/lib/api/intake";
import type { KnowledgeItemDetail } from "@/lib/api/second-brain";
import { intakeKeys } from "@/lib/query-keys";

export type DocumentPaneProps = {
  item: KnowledgeItemDetail;
  /** Public id of the intake item holding the extracted text, when known. */
  intakeItemId: string | null;
};

/**
 * The workspace's left half: the document, and its extracted text underneath.
 *
 * Extraction is capped at 200,000 characters server-side and windowed at
 * 20,000 per request, so this pages with `offset` and follows `next_offset`
 * verbatim rather than computing the next window itself.
 *
 * The text scrolls inside its own container. The page body must never scroll
 * horizontally or grow unboundedly tall because a document happened to be long.
 *
 * Every string rendered here is document-derived and therefore untrusted. All
 * of it goes through JSX text interpolation, which escapes it: a prompt
 * injection or an HTML/script payload in the source renders as inert visible
 * characters and can neither execute nor restyle the page.
 */
export function DocumentPane({ item, intakeItemId }: DocumentPaneProps) {
  const [offset, setOffset] = useState(0);

  const extractionQuery = useQuery({
    queryKey: intakeKeys.extraction(
      intakeItemId ?? "",
      offset,
      MAX_EXTRACTION_WINDOW_CHARACTERS,
    ),
    queryFn: () =>
      getIntakeExtraction(intakeItemId as string, {
        offset,
        limit: MAX_EXTRACTION_WINDOW_CHARACTERS,
      }),
    enabled: intakeItemId !== null,
  });

  const extraction = extractionQuery.data ?? null;

  return (
    <div className="flex h-full min-h-0 flex-col">
      <header className="shrink-0 space-y-2 border-b border-border-subtle px-4 py-3 sm:px-5">
        <div className="flex items-start gap-3">
          <IconChip
            icon={item.source.type === "link" ? Link2 : FileText}
            accent="secondBrain"
            size="lg"
          />
          <div className="min-w-0 flex-1">
            <h2 className="truncate text-h4 text-text-primary">{item.title}</h2>
            <SourceLine source={item.source} />
          </div>
        </div>
        {item.summary ? (
          <p className="line-clamp-3 text-body text-text-secondary">
            {item.summary}
          </p>
        ) : null}
      </header>

      <div className="flex min-h-0 flex-1 flex-col">
        <div className="flex shrink-0 flex-wrap items-center justify-between gap-2 px-4 py-2.5 sm:px-5">
          <h3 className="flex items-center gap-2 text-label text-text-secondary">
            <ScrollText
              aria-hidden="true"
              className="size-4 text-status-research"
            />
            Extracted text
          </h3>
          {extraction && extraction.has_extraction ? (
            <Badge variant="neutral">
              <span className="tabular-nums">
                {(extraction.offset + 1).toLocaleString()}-
                {(
                  extraction.offset + extraction.returned_characters
                ).toLocaleString()}
              </span>{" "}
              of{" "}
              <span className="tabular-nums">
                {extraction.total_characters.toLocaleString()}
              </span>
            </Badge>
          ) : null}
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-4 sm:px-5">
          {intakeItemId === null ? (
            <Alert variant="info" title="No extracted text is linked here">
              This item is not linked to a processed capture, so there is no
              extracted text to show. The chat and the ready actions still work:
              they resolve the document on the server.
            </Alert>
          ) : extractionQuery.isPending ? (
            <div className="space-y-2">
              <Skeleton className="h-4 rounded-md" />
              <Skeleton className="h-4 rounded-md" />
              <Skeleton className="h-4 w-4/5 rounded-md" />
              <Skeleton className="h-4 rounded-md" />
              <Skeleton className="h-4 w-2/3 rounded-md" />
            </div>
          ) : extractionQuery.isError ? (
            <ErrorState
              title="The text couldn't load"
              onRetry={() => void extractionQuery.refetch()}
            />
          ) : !extraction || !extraction.has_extraction ? (
            <EmptyState
              icon={ScrollText}
              title="No text was extracted"
              description="This source produced no readable text. You can still read the original from the link above."
            />
          ) : (
            <article
              className={cn(
                "whitespace-pre-wrap break-words rounded-md border border-border-subtle bg-bg-surface p-4",
                "text-body leading-relaxed text-text-secondary",
              )}
            >
              {extraction.text}
            </article>
          )}
        </div>

        {extraction && extraction.has_extraction ? (
          <div className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-border-subtle px-4 py-2.5 sm:px-5">
            <p className="text-caption tabular-nums text-text-muted">
              {extraction.content_type ?? "text"}
            </p>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={extraction.offset === 0}
                onClick={() =>
                  setOffset(
                    Math.max(
                      0,
                      extraction.offset - MAX_EXTRACTION_WINDOW_CHARACTERS,
                    ),
                  )
                }
              >
                Previous
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={
                  !extraction.has_more || extraction.next_offset === null
                }
                onClick={() => setOffset(extraction.next_offset ?? offset)}
              >
                Next
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

function SourceLine({ source }: { source: KnowledgeItemDetail["source"] }) {
  if (source.type === "link" && source.url) {
    return (
      <a
        href={source.url}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex max-w-full items-center gap-1.5 text-caption text-brand-primary hover:underline"
      >
        <span className="truncate">{source.url}</span>
        <ExternalLink aria-hidden="true" className="size-3 shrink-0" />
      </a>
    );
  }

  if (source.type === "resource" && source.resource) {
    return (
      <p className="truncate text-caption text-text-muted">
        From your file: {source.resource.title}
      </p>
    );
  }

  return <p className="text-caption text-text-muted">No linked source</p>;
}
