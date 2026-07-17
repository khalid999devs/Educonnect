"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  Spinner,
} from "@educonnect/ui";
import {
  CheckCircle2,
  CircleAlert,
  CircleSlash,
  ExternalLink,
  FileText,
  Link2,
  RotateCcw,
  X,
} from "lucide-react";

import {
  INTAKE_PIPELINE,
  isProcessing,
  type IntakeItem,
  type IntakeState,
} from "@/lib/api/intake";

const STATE_LABELS: Record<IntakeState, string> = {
  uploaded_or_linked: "Received",
  queued: "Queued",
  extracting: "Extracting text",
  extracted: "Text extracted",
  organizing: "Organizing",
  awaiting_review: "Awaiting your review",
  confirmed: "Confirmed",
  saved: "Saved",
  failed_retryable: "Failed — can retry",
  failed_final: "Failed",
  cancelled: "Cancelled",
};

const FAILURE_MESSAGES: Record<string, string> = {
  unsafe_url: "That link was blocked by our safety checks.",
  link_fetch_failed: "We couldn't reach that link.",
  link_http_client_error: "The link returned a client error (4xx).",
  link_http_server_error: "The link's server returned an error (5xx).",
  content_too_large: "The content was larger than we can process.",
  unsupported_content_type: "That content type isn't supported for extraction.",
  file_unavailable: "The source file was no longer available.",
  extraction_failed: "We couldn't extract readable text from the source.",
  attempts_exhausted: "We tried several times without success.",
  classification_failed: "We couldn't organize the extracted text.",
};

const STEP_LABELS: Record<string, string> = {
  queued: "Queued",
  extracting: "Extract",
  extracted: "Extracted",
  organizing: "Organize",
  awaiting_review: "Review",
  saved: "Saved",
};

function Stepper({ item }: { item: IntakeItem }) {
  const failed = item.state === "failed_final" || item.state === "cancelled";
  const currentIndex = INTAKE_PIPELINE.indexOf(item.state);

  return (
    <ol className="flex flex-wrap items-center gap-x-1 gap-y-2">
      {INTAKE_PIPELINE.map((step, index) => {
        const reached = currentIndex >= index && currentIndex !== -1;
        const active = item.state === step;

        return (
          <li key={step} className="flex items-center gap-1">
            <span
              className={cn(
                "flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-caption",
                active && !failed
                  ? "border-brand-primary/40 bg-bg-interactive text-brand-primary"
                  : reached && !failed
                    ? "border-status-success/40 text-status-success"
                    : "border-border-default text-text-muted",
              )}
            >
              {reached && !active && !failed ? (
                <CheckCircle2 aria-hidden="true" className="size-3.5" />
              ) : null}
              {active && isProcessing(item.state) ? (
                <Spinner size="sm" className="size-3.5 text-brand-primary" />
              ) : null}
              {STEP_LABELS[step]}
            </span>
            {index < INTAKE_PIPELINE.length - 1 ? (
              <span aria-hidden="true" className="text-text-muted">
                ·
              </span>
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}

/** One intake item's observable status: source, the pipeline stepper, the
 * extraction evidence (shown before any suggestions), the truthful event
 * timeline, and cancel/retry actions. */
export function IntakeDetail({
  item,
  busy,
  onCancel,
  onRetry,
  children,
}: {
  item: IntakeItem;
  busy: boolean;
  onCancel: () => void;
  onRetry: () => void;
  children?: React.ReactNode;
}) {
  const failed =
    item.state === "failed_final" || item.state === "failed_retryable";

  return (
    <Card>
      <CardHeader className="space-y-3">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <CardTitle className="flex min-w-0 items-center gap-2">
            {item.source.type === "link" ? (
              <Link2
                aria-hidden="true"
                className="size-5 shrink-0 text-status-info"
              />
            ) : (
              <FileText
                aria-hidden="true"
                className="size-5 shrink-0 text-brand-primary"
              />
            )}
            <span className="truncate">
              {item.source.type === "link"
                ? (item.source.url ?? "Linked source")
                : (item.source.resource?.title ?? "File source")}
            </span>
          </CardTitle>
          <Badge
            variant={
              item.state === "saved"
                ? "success"
                : failed || item.state === "cancelled"
                  ? "neutral"
                  : "info"
            }
          >
            {STATE_LABELS[item.state]}
          </Badge>
        </div>

        <Stepper item={item} />
      </CardHeader>

      <CardContent className="space-y-4">
        {item.context ? (
          <p className="text-body text-text-secondary">
            <span className="font-medium text-text-primary">Your note: </span>
            {item.context}
          </p>
        ) : null}

        {item.failure_code ? (
          <Alert
            variant={item.state === "failed_retryable" ? "warning" : "error"}
            title={STATE_LABELS[item.state]}
          >
            {FAILURE_MESSAGES[item.failure_code] ??
              "Processing could not be completed."}
          </Alert>
        ) : null}

        {/* Extraction evidence precedes any AI preview (doc 12). */}
        {item.extraction ? (
          <div className="flex flex-wrap items-center gap-2 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-2">
            <CheckCircle2
              aria-hidden="true"
              className="size-4 text-status-success"
            />
            <span className="text-body text-text-secondary">
              Extracted{" "}
              <span className="font-medium text-text-primary tabular-nums">
                {item.extraction.characters.toLocaleString()}
              </span>{" "}
              characters from{" "}
              <span className="font-mono text-caption">
                {item.extraction.content_type}
              </span>
            </span>
          </div>
        ) : null}

        {item.classification ? (
          <p className="text-caption text-text-muted">
            Organized by {item.classification.provider} ·{" "}
            {item.classification.model} · schema{" "}
            {item.classification.schema_version} ·{" "}
            {item.classification.latency_ms} ms. AI can be wrong — review below.
          </p>
        ) : null}

        {children}

        {item.events.length > 0 ? (
          <details className="rounded-md border border-border-subtle">
            <summary className="cursor-pointer px-3 py-2 text-caption font-medium text-text-muted">
              Activity ({item.events.length})
            </summary>
            <ol className="space-y-1.5 px-3 pb-3">
              {item.events.map((event, index) => (
                <li key={index} className="flex gap-2 text-caption">
                  <span aria-hidden="true" className="text-text-muted">
                    •
                  </span>
                  <span className="text-text-secondary">
                    {event.detail ??
                      `${event.from_state ?? "—"} → ${event.to_state ?? "—"}`}
                  </span>
                </li>
              ))}
            </ol>
          </details>
        ) : null}

        <div className="flex flex-wrap items-center gap-2">
          {isProcessing(item.state) ? (
            <Button
              variant="ghost"
              size="sm"
              disabled={busy}
              onClick={onCancel}
            >
              <X aria-hidden="true" className="size-4" />
              Cancel
            </Button>
          ) : null}
          {item.state === "failed_retryable" ? (
            <Button
              variant="secondary"
              size="sm"
              disabled={busy}
              onClick={onRetry}
            >
              <RotateCcw aria-hidden="true" className="size-4" />
              Retry
            </Button>
          ) : null}
          {item.state === "cancelled" ? (
            <span className="flex items-center gap-1.5 text-caption text-text-muted">
              <CircleSlash aria-hidden="true" className="size-4" />
              Cancelled
            </span>
          ) : null}
          {item.state === "failed_final" ? (
            <span className="flex items-center gap-1.5 text-caption text-status-error">
              <CircleAlert aria-hidden="true" className="size-4" />
              This item can't be processed further.
            </span>
          ) : null}
          {item.source.type === "link" && item.source.url ? (
            <a
              href={item.source.url}
              target="_blank"
              rel="noopener noreferrer"
              className="ml-auto flex items-center gap-1 text-caption text-brand-primary hover:underline"
            >
              Open source
              <ExternalLink aria-hidden="true" className="size-3" />
            </a>
          ) : null}
        </div>
      </CardContent>
    </Card>
  );
}
