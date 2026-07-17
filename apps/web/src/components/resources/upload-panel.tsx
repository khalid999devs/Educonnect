"use client";

import {
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
  CircleAlert,
  CircleCheck,
  CircleX,
  FileText,
  UploadCloud,
} from "lucide-react";

import type { UploadJob } from "./use-uploads";

const STATE_LABELS: Record<UploadJob["state"], string> = {
  hashing: "Preparing (checksum)",
  requesting: "Requesting secure upload",
  uploading: "Uploading",
  confirming: "Verifying with the server",
  done: "Ready in your library",
  failed: "Failed",
  cancelled: "Cancelled",
};

function formatBytes(size: number): string {
  if (size >= 1_048_576) {
    return `${(size / 1_048_576).toFixed(1)} MB`;
  }

  return `${Math.max(1, Math.round(size / 1024))} KB`;
}

/** Live view of the real upload lifecycle — every state here is a genuine
 * transport state with a working cancel/retry, never a simulation. */
export function UploadPanel({
  jobs,
  onRetry,
  onCancel,
  onDismiss,
}: {
  jobs: UploadJob[];
  onRetry: (job: UploadJob) => void;
  onCancel: (job: UploadJob) => void;
  onDismiss: (job: UploadJob) => void;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <UploadCloud
            aria-hidden="true"
            className="size-5 text-brand-primary"
          />
          Uploads
        </CardTitle>
      </CardHeader>
      <CardContent>
        {jobs.length === 0 ? (
          <p className="text-body text-text-muted">
            Files you add appear here with real progress, and you can cancel or
            retry at any step.
          </p>
        ) : (
          <ul className="space-y-3">
            {jobs.map((job) => {
              const active =
                job.state === "hashing" ||
                job.state === "requesting" ||
                job.state === "uploading" ||
                job.state === "confirming";

              return (
                <li
                  key={job.key}
                  className="rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-2.5"
                >
                  <div className="flex items-center gap-2">
                    {job.state === "done" ? (
                      <CircleCheck
                        aria-hidden="true"
                        className="size-4 shrink-0 text-status-success"
                      />
                    ) : job.state === "failed" ? (
                      <CircleAlert
                        aria-hidden="true"
                        className="size-4 shrink-0 text-status-error"
                      />
                    ) : job.state === "cancelled" ? (
                      <CircleX
                        aria-hidden="true"
                        className="size-4 shrink-0 text-text-muted"
                      />
                    ) : (
                      <FileText
                        aria-hidden="true"
                        className="size-4 shrink-0 text-text-muted"
                      />
                    )}
                    <span className="min-w-0 flex-1 truncate text-body font-medium text-text-primary">
                      {job.fileName}
                    </span>
                    <span className="shrink-0 text-caption tabular-nums text-text-muted">
                      {formatBytes(job.size)}
                    </span>
                  </div>

                  <div className="mt-1.5 flex items-center gap-2">
                    {active ? (
                      <Spinner size="sm" className="text-brand-primary" />
                    ) : null}
                    <span
                      className={cn(
                        "text-caption",
                        job.state === "failed"
                          ? "text-status-error"
                          : job.state === "done"
                            ? "text-status-success"
                            : "text-text-muted",
                      )}
                    >
                      {STATE_LABELS[job.state]}
                      {job.state === "uploading" && job.percent !== null
                        ? ` · ${job.percent}%`
                        : ""}
                    </span>
                  </div>

                  {job.state === "uploading" ? (
                    <div
                      role="progressbar"
                      aria-label={`Upload progress for ${job.fileName}`}
                      aria-valuemin={0}
                      aria-valuemax={100}
                      aria-valuenow={job.percent ?? 0}
                      className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-bg-interactive"
                    >
                      <div
                        className="h-full rounded-full bg-brand-primary transition-[width]"
                        style={{ width: `${job.percent ?? 0}%` }}
                      />
                    </div>
                  ) : null}

                  {job.message ? (
                    <p className="mt-1.5 text-caption text-status-error">
                      {job.message}
                    </p>
                  ) : null}

                  <div className="mt-2 flex flex-wrap items-center gap-2">
                    {job.state === "uploading" ? (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onCancel(job)}
                      >
                        Cancel
                      </Button>
                    ) : null}
                    {job.state === "failed" ? (
                      <>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => onRetry(job)}
                        >
                          Retry upload
                        </Button>
                        {job.resource ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onCancel(job)}
                          >
                            Discard
                          </Button>
                        ) : null}
                      </>
                    ) : null}
                    {job.state === "done" || job.state === "cancelled" ? (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDismiss(job)}
                      >
                        Dismiss
                      </Button>
                    ) : null}
                    {job.state === "done" && job.resource ? (
                      <Badge variant="success">
                        {job.resource.file?.verified_mime_type ?? "Verified"}
                      </Badge>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
