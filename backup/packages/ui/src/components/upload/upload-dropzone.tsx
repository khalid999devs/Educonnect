"use client";

import {
  CircleAlert,
  CircleCheck,
  FileText,
  Lock,
  UploadCloud,
} from "lucide-react";
import { useRef, useState, type DragEvent } from "react";

import { cn } from "../../lib/cn";
import { Spinner } from "../feedback/spinner";
import { Button } from "../primitives/button";

export type UploadStatus = "idle" | "uploading" | "success" | "error";

export type UploadDropzoneProps = {
  status: UploadStatus;
  onFilesSelected: (files: File[]) => void;
  /** Human description of accepted types, e.g. "PDF, DOCX, or images". */
  acceptDescription: string;
  /** Human description of the size limit, e.g. "Up to 25 MB per file". */
  maxSizeDescription: string;
  /** Shown with a lock icon; uploads must state privacy handling (doc 07). */
  privacyNote?: string;
  fileName?: string;
  /** 0-100 while uploading; omit for indeterminate. */
  progress?: number;
  errorMessage?: string;
  onCancel?: () => void;
  onRetry?: () => void;
  onReset?: () => void;
  accept?: string;
  multiple?: boolean;
  disabled?: boolean;
  className?: string;
};

/**
 * Presentational upload surface with the required type/size limits,
 * progress, cancel/retry, privacy note, and recoverable error states.
 * Transport is owned by the caller; this component never fakes progress.
 */
export function UploadDropzone({
  status,
  onFilesSelected,
  acceptDescription,
  maxSizeDescription,
  privacyNote,
  fileName,
  progress,
  errorMessage,
  onCancel,
  onRetry,
  onReset,
  accept,
  multiple = false,
  disabled = false,
  className,
}: UploadDropzoneProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [isDragActive, setIsDragActive] = useState(false);

  const interactive = status === "idle" && !disabled;

  const selectFiles = (list: FileList | null) => {
    if (!list || list.length === 0) {
      return;
    }

    onFilesSelected(Array.from(list));
  };

  const onDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setIsDragActive(false);

    if (interactive) {
      selectFiles(event.dataTransfer.files);
    }
  };

  return (
    <div className={className}>
      <div
        onDragOver={(event) => {
          event.preventDefault();

          if (interactive) {
            setIsDragActive(true);
          }
        }}
        onDragLeave={() => setIsDragActive(false)}
        onDrop={onDrop}
        className={cn(
          "flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border-default bg-bg-surface px-6 py-10 text-center transition-colors",
          isDragActive && "border-brand-focus bg-bg-interactive",
          disabled && "opacity-50",
        )}
      >
        <input
          ref={inputRef}
          type="file"
          className="sr-only"
          tabIndex={-1}
          accept={accept}
          multiple={multiple}
          disabled={!interactive}
          onChange={(event) => {
            selectFiles(event.target.files);
            event.target.value = "";
          }}
        />

        {status === "idle" ? (
          <>
            <span className="flex size-12 items-center justify-center rounded-full bg-bg-interactive">
              <UploadCloud
                aria-hidden="true"
                className="size-6 text-brand-primary"
              />
            </span>
            <p className="text-body font-medium text-text-primary">
              Drag and drop, or browse your files
            </p>
            <Button
              variant="secondary"
              size="md"
              disabled={disabled}
              onClick={() => inputRef.current?.click()}
            >
              Browse files
            </Button>
          </>
        ) : null}

        {status === "uploading" ? (
          <>
            <Spinner size="md" className="text-brand-primary" />
            <p className="flex items-center gap-2 text-body font-medium text-text-primary">
              <FileText aria-hidden="true" className="size-4 text-text-muted" />
              {fileName ?? "Uploading"}
            </p>
            <div className="w-full max-w-xs space-y-2">
              <div
                role="progressbar"
                aria-label="Upload progress"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={progress}
                className="h-2 w-full overflow-hidden rounded-full bg-bg-interactive"
              >
                <div
                  className="h-full rounded-full bg-brand-primary transition-[width]"
                  style={{ width: `${progress ?? 0}%` }}
                />
              </div>
              {typeof progress === "number" ? (
                <p className="text-caption tabular-nums text-text-muted">
                  {Math.round(progress)}%
                </p>
              ) : null}
            </div>
            {onCancel ? (
              <Button variant="ghost" size="sm" onClick={onCancel}>
                Cancel upload
              </Button>
            ) : null}
          </>
        ) : null}

        {status === "success" ? (
          <>
            <span className="flex size-12 items-center justify-center rounded-full bg-status-success/10">
              <CircleCheck
                aria-hidden="true"
                className="size-6 text-status-success"
              />
            </span>
            <p className="text-body font-medium text-text-primary">
              {fileName ? `${fileName} uploaded` : "Upload complete"}
            </p>
            {onReset ? (
              <Button variant="secondary" size="md" onClick={onReset}>
                Upload another file
              </Button>
            ) : null}
          </>
        ) : null}

        {status === "error" ? (
          <>
            <span className="flex size-12 items-center justify-center rounded-full bg-status-error/10">
              <CircleAlert
                aria-hidden="true"
                className="size-6 text-status-error"
              />
            </span>
            <p className="text-body font-medium text-text-primary">
              {fileName ? `${fileName} failed to upload` : "Upload failed"}
            </p>
            {errorMessage ? (
              <p className="max-w-sm text-caption text-status-error">
                {errorMessage}
              </p>
            ) : null}
            <div className="flex items-center gap-2">
              {onRetry ? (
                <Button variant="secondary" size="md" onClick={onRetry}>
                  Retry upload
                </Button>
              ) : null}
              {onReset ? (
                <Button variant="ghost" size="md" onClick={onReset}>
                  Choose a different file
                </Button>
              ) : null}
            </div>
          </>
        ) : null}

        <p aria-live="polite" className="sr-only">
          {status === "uploading" && typeof progress === "number"
            ? `Uploading, ${Math.round(progress)} percent complete`
            : null}
          {status === "success" ? "Upload complete" : null}
          {status === "error" ? "Upload failed" : null}
        </p>
      </div>

      <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
        <p className="text-caption text-text-muted">
          {acceptDescription} · {maxSizeDescription}
        </p>
        {privacyNote ? (
          <p className="flex items-center gap-1 text-caption text-text-muted">
            <Lock aria-hidden="true" className="size-3" />
            {privacyNote}
          </p>
        ) : null}
      </div>
    </div>
  );
}
