"use client";

import {
  Alert,
  Button,
  CardTitle,
  cn,
  FormField,
  Input,
  Textarea,
  UploadDropzone,
  type UploadStatus,
} from "@educonnect/ui";
import { useMutation } from "@tanstack/react-query";
import { Library, Link2, Sparkles, UploadCloud } from "lucide-react";
import { useRef, useState, type FormEvent } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { ApiError } from "@/lib/api/http";
import { createFileIntake, createLinkIntake } from "@/lib/api/intake";
import {
  confirmResourceUpload,
  initiateFileResource,
  putToUploadGrant,
  resolveResourceMimeType,
  sha256Hex,
  ALLOWED_RESOURCE_MIME_TYPES,
  MAX_RESOURCE_FILE_BYTES,
  RESOURCE_FILE_ACCEPT,
  RESOURCE_FILE_ACCEPT_DESCRIPTION,
  type AllowedResourceMimeType,
} from "@/lib/api/resources";
import { SourcePicker } from "./source-picker";

/** What a completed capture hands back to the flow. `resourceId` is null for
 * a link capture: a link intake never creates a Resource, so there is nothing
 * to file into a course directory unless the review step creates one. */
export type CaptureResult = {
  intakeItemId: string;
  resourceId: string | null;
  label: string;
};

export type CapturePanelProps = {
  onCaptured: (result: CaptureResult) => void;
};

type Mode = "file" | "link" | "source";

const MODES: { value: Mode; label: string; icon: typeof UploadCloud }[] = [
  { value: "file", label: "File", icon: UploadCloud },
  { value: "link", label: "Link", icon: Link2 },
  { value: "source", label: "Source", icon: Library },
];

function rejectionFor(file: File): string | null {
  if (file.size === 0) {
    return "That file is empty.";
  }

  if (file.size > MAX_RESOURCE_FILE_BYTES) {
    return "That file is larger than the 25 MB limit.";
  }

  const mimeType = resolveResourceMimeType(file);

  if (!(ALLOWED_RESOURCE_MIME_TYPES as readonly string[]).includes(mimeType)) {
    return `That file type can't be read. Accepted: ${RESOURCE_FILE_ACCEPT_DESCRIPTION}.`;
  }

  return null;
}

/**
 * Step one: get started, three ways.
 *
 * A file goes through the strict grant -> PUT -> confirm upload lifecycle and
 * then into the intake pipeline; a link goes straight into it; a source picks
 * something already in Resources and brings it in. All three land on the same
 * intake item, which is what every later step polls. Nothing here opens a
 * dialog - the whole flow is inline by design.
 *
 * This renders headless (no card of its own) so it can share one surface with
 * the library browse beneath it.
 */
export function CapturePanel({ onCaptured }: CapturePanelProps) {
  const [mode, setMode] = useState<Mode>("file");
  const [url, setUrl] = useState("");
  const [context, setContext] = useState("");
  const [progress, setProgress] = useState(0);
  const [fileName, setFileName] = useState<string | null>(null);
  const [rejection, setRejection] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const uploadMutation = useMutation({
    mutationFn: async (file: File) => {
      const controller = new AbortController();
      abortRef.current = controller;

      const mimeType = resolveResourceMimeType(file) as AllowedResourceMimeType;
      const { resource, upload } = await initiateFileResource({
        title: file.name.slice(0, 160),
        original_name: file.name,
        mime_type: mimeType,
        size: file.size,
        sha256: await sha256Hex(file),
        // Filing happens later, once the student has chosen a directory.
        course_id: null,
      });

      await putToUploadGrant(upload, file, {
        signal: controller.signal,
        onProgress: setProgress,
      });

      const confirmed = await confirmResourceUpload(
        resource.id,
        resource.version,
      );
      const item = await createFileIntake(
        confirmed.id,
        context.trim() === "" ? undefined : context.trim(),
      );

      return {
        intakeItemId: item.id,
        resourceId: confirmed.id,
        label: confirmed.title,
      } satisfies CaptureResult;
    },
    onSuccess: (result) => {
      abortRef.current = null;
      onCaptured(result);
    },
    onError: () => {
      abortRef.current = null;
    },
  });

  const linkMutation = useMutation({
    mutationFn: async () => {
      const item = await createLinkIntake(
        url.trim(),
        context.trim() === "" ? undefined : context.trim(),
      );

      return {
        intakeItemId: item.id,
        resourceId: null,
        label: item.source.url ?? url.trim(),
      } satisfies CaptureResult;
    },
    onSuccess: onCaptured,
  });

  const acceptFiles = (files: File[]) => {
    const file = files[0];

    if (!file) {
      return;
    }

    const invalid = rejectionFor(file);

    if (invalid) {
      setRejection(`${file.name}: ${invalid}`);

      return;
    }

    setRejection(null);
    setFileName(file.name);
    setProgress(0);
    uploadMutation.mutate(file);
  };

  const uploadStatus: UploadStatus = uploadMutation.isPending
    ? "uploading"
    : uploadMutation.isError
      ? "error"
      : "idle";

  const linkApiError =
    linkMutation.error instanceof ApiError ? linkMutation.error : null;

  const submitLink = (event: FormEvent) => {
    event.preventDefault();
    linkMutation.mutate();
  };

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        <CardTitle className="flex items-center gap-2.5">
          <IconChip icon={Sparkles} accent="secondBrain" />
          Get started with
        </CardTitle>
        <div
          role="group"
          aria-label="How to get started"
          className="grid grid-cols-3 gap-1 rounded-lg border border-border-default bg-bg-canvas p-1"
        >
          {MODES.map((option) => (
            <button
              key={option.value}
              type="button"
              aria-pressed={mode === option.value}
              onClick={() => setMode(option.value)}
              className={cn(
                "flex items-center justify-center gap-1.5 rounded-md px-2 py-2 text-button transition-colors",
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                mode === option.value
                  ? "bg-bg-elevated text-text-primary"
                  : "text-text-muted hover:text-text-primary",
              )}
            >
              <option.icon aria-hidden="true" className="size-4 shrink-0" />
              {option.label}
            </button>
          ))}
        </div>
      </div>

      {mode === "source" ? (
        <SourcePicker onCaptured={onCaptured} />
      ) : (
        <>
          <FormField
            label="What is this, and what do you want from it?"
            hint="Optional. It helps the pre-selection get your purpose right."
          >
            {(control) => (
              <Textarea
                {...control}
                value={context}
                maxLength={2000}
                onChange={(event) => setContext(event.target.value)}
                placeholder="e.g. Week 6 lecture slides, I have an exam on this"
                className="min-h-16"
              />
            )}
          </FormField>

          {mode === "file" ? (
            <>
              <UploadDropzone
                status={uploadStatus}
                onFilesSelected={acceptFiles}
                accept={RESOURCE_FILE_ACCEPT}
                acceptDescription={RESOURCE_FILE_ACCEPT_DESCRIPTION}
                maxSizeDescription="Up to 25 MB per file"
                privacyNote="Private to you. Never used to train models."
                fileName={fileName ?? undefined}
                progress={progress}
                errorMessage={uploadMutation.error?.message}
                onCancel={() => abortRef.current?.abort()}
                onRetry={() => uploadMutation.reset()}
                onReset={() => {
                  uploadMutation.reset();
                  setFileName(null);
                  setProgress(0);
                }}
              />
              {rejection ? (
                <Alert variant="warning" title="That file can't be read">
                  {rejection}
                </Alert>
              ) : null}
            </>
          ) : (
            <form onSubmit={submitLink} className="space-y-3">
              <FormField
                label="Link"
                required
                hint="HTTPS only. Blocked, private, or paywalled pages can't be read."
                error={linkApiError?.fieldError("url")}
              >
                {(control) => (
                  <Input
                    {...control}
                    type="url"
                    value={url}
                    maxLength={2048}
                    onChange={(event) => setUrl(event.target.value)}
                    placeholder="https://…"
                  />
                )}
              </FormField>
              {linkMutation.error && !linkApiError ? (
                <Alert variant="error" title="Couldn't read that link">
                  {linkMutation.error.message}
                </Alert>
              ) : null}
              <Button
                type="submit"
                isLoading={linkMutation.isPending}
                loadingLabel="Reading"
                disabled={url.trim() === ""}
              >
                Capture this link
              </Button>
            </form>
          )}
        </>
      )}
    </div>
  );
}
