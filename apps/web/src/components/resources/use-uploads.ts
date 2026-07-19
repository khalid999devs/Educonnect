"use client";

import { useCallback, useReducer, useRef } from "react";

import { ApiError } from "@/lib/api/http";
import {
  ALLOWED_RESOURCE_MIME_TYPES,
  cancelResourceUpload,
  confirmResourceUpload,
  initiateFileResource,
  MAX_RESOURCE_FILE_BYTES,
  putToUploadGrant,
  retryResourceUpload,
  sha256Hex,
  type AllowedResourceMimeType,
  type Resource,
} from "@/lib/api/resources";

/**
 * Honest client half of the strict upload lifecycle: initiate (metadata +
 * sha256) → raw signature-bound PUT → confirm. Every state shown to the
 * user is a real transport state; progress comes from the browser's own
 * upload events, never a timer.
 */

export type UploadState =
  | "hashing"
  | "requesting"
  | "uploading"
  | "confirming"
  | "done"
  | "failed"
  | "cancelled";

export type UploadJob = {
  key: string;
  fileName: string;
  size: number;
  state: UploadState;
  /** Real transfer percent; null while not uploading. */
  percent: number | null;
  message: string | null;
  resource: Resource | null;
};

export type UploadsAction =
  | { type: "added"; key: string; fileName: string; size: number }
  | { type: "state"; key: string; state: UploadState }
  | { type: "resource"; key: string; resource: Resource }
  | { type: "progress"; key: string; percent: number }
  | { type: "failed"; key: string; message: string }
  | { type: "dismissed"; key: string };

export function uploadsReducer(
  jobs: UploadJob[],
  action: UploadsAction,
): UploadJob[] {
  switch (action.type) {
    case "added":
      return [
        ...jobs,
        {
          key: action.key,
          fileName: action.fileName,
          size: action.size,
          state: "hashing",
          percent: null,
          message: null,
          resource: null,
        },
      ];
    case "state":
      return jobs.map((job) =>
        job.key === action.key
          ? {
              ...job,
              state: action.state,
              percent: action.state === "uploading" ? (job.percent ?? 0) : null,
              message: null,
            }
          : job,
      );
    case "resource":
      return jobs.map((job) =>
        job.key === action.key ? { ...job, resource: action.resource } : job,
      );
    case "progress":
      return jobs.map((job) =>
        job.key === action.key && job.state === "uploading"
          ? { ...job, percent: action.percent }
          : job,
      );
    case "failed":
      /* A cancelled or completed job can never regress to failed - the
         abort path may reject transport promises after cancellation. */
      return jobs.map((job) =>
        job.key === action.key &&
        job.state !== "cancelled" &&
        job.state !== "done"
          ? { ...job, state: "failed", percent: null, message: action.message }
          : job,
      );
    case "dismissed":
      return jobs.filter((job) => job.key !== action.key);
  }
}

export type FileValidationError = { code: "type" | "size"; message: string };

/** Client-side pre-check mirroring the initiate contract's limits. */
export function validateResourceFile(file: File): FileValidationError | null {
  if (!(ALLOWED_RESOURCE_MIME_TYPES as readonly string[]).includes(file.type)) {
    return {
      code: "type",
      message:
        "Only PDF, JPEG, PNG, WebP, plain-text, and Markdown files are supported.",
    };
  }

  if (file.size < 1 || file.size > MAX_RESOURCE_FILE_BYTES) {
    return {
      code: "size",
      message: "Files must be between 1 byte and 25 MB.",
    };
  }

  return null;
}

/** Title derived from the filename; the user can edit metadata afterwards. */
export function titleFromFileName(fileName: string): string {
  const base = fileName.replace(/\.[^.]+$/, "").trim();
  const title = base === "" ? fileName.trim() : base;

  return title.slice(0, 160);
}

function failureMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "The upload failed unexpectedly.";
}

type JobMeta = { courseId: string | null; topic: string | null };

export function useUploads({ onSettled }: { onSettled: () => void }) {
  const [jobs, dispatch] = useReducer(uploadsReducer, []);
  const filesRef = useRef(new Map<string, { file: File; meta: JobMeta }>());
  const abortsRef = useRef(new Map<string, AbortController>());
  const counterRef = useRef(0);

  const runTransfer = useCallback(
    async (
      key: string,
      file: File,
      resource: Resource,
      grant: Parameters<typeof putToUploadGrant>[0],
    ) => {
      const controller = new AbortController();

      abortsRef.current.set(key, controller);
      dispatch({ type: "state", key, state: "uploading" });

      try {
        await putToUploadGrant(grant, file, {
          signal: controller.signal,
          onProgress: (percent) => dispatch({ type: "progress", key, percent }),
        });
      } finally {
        abortsRef.current.delete(key);
      }

      dispatch({ type: "state", key, state: "confirming" });
      const confirmed = await confirmResourceUpload(
        resource.id,
        resource.version,
      );

      dispatch({ type: "resource", key, resource: confirmed });
      dispatch({ type: "state", key, state: "done" });
      filesRef.current.delete(key);
      onSettled();
    },
    [onSettled],
  );

  const startUpload = useCallback(
    async (file: File, meta: JobMeta) => {
      counterRef.current += 1;
      const key = `upload-${counterRef.current}`;

      filesRef.current.set(key, { file, meta });
      dispatch({ type: "added", key, fileName: file.name, size: file.size });

      try {
        const sha256 = await sha256Hex(file);

        dispatch({ type: "state", key, state: "requesting" });
        const { resource, upload } = await initiateFileResource({
          title: titleFromFileName(file.name),
          course_id: meta.courseId,
          topic: meta.topic,
          original_name: file.name,
          mime_type: file.type as AllowedResourceMimeType,
          size: file.size,
          sha256,
        });

        dispatch({ type: "resource", key, resource });
        await runTransfer(key, file, resource, upload);
      } catch (error) {
        if (abortsRef.current.get(key)?.signal.aborted !== true) {
          dispatch({ type: "failed", key, message: failureMessage(error) });
        }

        onSettled();
      }
    },
    [onSettled, runTransfer],
  );

  /**
   * Finish a server-side pending upload from a fresh file pick (e.g. after
   * a reload lost the original File). The server re-verifies the checksum
   * on confirm, so a mismatched file fails honestly.
   */
  const resumeUpload = useCallback(
    async (resource: Resource, file: File) => {
      counterRef.current += 1;
      const key = `upload-${counterRef.current}`;

      filesRef.current.set(key, {
        file,
        meta: { courseId: resource.course?.id ?? null, topic: resource.topic },
      });
      dispatch({ type: "added", key, fileName: file.name, size: file.size });
      dispatch({ type: "resource", key, resource });

      try {
        dispatch({ type: "state", key, state: "requesting" });
        const renewed = await retryResourceUpload(
          resource.id,
          resource.version,
        );

        dispatch({ type: "resource", key, resource: renewed.resource });
        await runTransfer(key, file, renewed.resource, renewed.upload);
      } catch (error) {
        if (abortsRef.current.get(key)?.signal.aborted !== true) {
          dispatch({ type: "failed", key, message: failureMessage(error) });
        }

        onSettled();
      }
    },
    [onSettled, runTransfer],
  );

  const retryJob = useCallback(
    async (job: UploadJob) => {
      const entry = filesRef.current.get(job.key);

      if (!entry) {
        return;
      }

      try {
        if (job.resource === null) {
          /* Initiate never succeeded - start the lifecycle from scratch. */
          filesRef.current.delete(job.key);
          dispatch({ type: "dismissed", key: job.key });
          await startUpload(entry.file, entry.meta);
          return;
        }

        dispatch({ type: "state", key: job.key, state: "requesting" });
        const { resource, upload } = await retryResourceUpload(
          job.resource.id,
          job.resource.version,
        );

        dispatch({ type: "resource", key: job.key, resource });
        await runTransfer(job.key, entry.file, resource, upload);
      } catch (error) {
        dispatch({
          type: "failed",
          key: job.key,
          message: failureMessage(error),
        });
        onSettled();
      }
    },
    [onSettled, runTransfer, startUpload],
  );

  const cancelJob = useCallback(
    async (job: UploadJob) => {
      abortsRef.current.get(job.key)?.abort();
      filesRef.current.delete(job.key);

      if (job.resource !== null) {
        try {
          await cancelResourceUpload(job.resource.id, job.resource.version);
        } catch {
          /* The pending row stays visible in the library for cleanup. */
        }
      }

      dispatch({ type: "state", key: job.key, state: "cancelled" });
      onSettled();
    },
    [onSettled],
  );

  const dismissJob = useCallback((job: UploadJob) => {
    filesRef.current.delete(job.key);
    dispatch({ type: "dismissed", key: job.key });
  }, []);

  return { jobs, startUpload, resumeUpload, retryJob, cancelJob, dismissJob };
}
