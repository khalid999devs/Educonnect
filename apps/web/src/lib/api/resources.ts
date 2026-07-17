import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

/** Exact runtime shapes of the resources contract (private library, strict
 * grant→PUT→confirm upload lifecycle) from openapi.yaml. */

const isoDateTime = z.string();

export const ALLOWED_RESOURCE_MIME_TYPES = [
  "application/pdf",
  "image/jpeg",
  "image/png",
  "image/webp",
  "text/plain",
  "text/markdown",
] as const;

export type AllowedResourceMimeType =
  (typeof ALLOWED_RESOURCE_MIME_TYPES)[number];

export const MAX_RESOURCE_FILE_BYTES = 26_214_400;

export const storedFileSchema = z.object({
  public_id: z.string(),
  original_name: z.string(),
  declared_mime_type: z.string(),
  verified_mime_type: z.string().nullable(),
  expected_size: z.number().int(),
  verified_size: z.number().int().nullable(),
  status: z.enum(["pending", "ready", "deletion_pending"]),
  ready_at: isoDateTime.nullable(),
});

export type StoredFile = z.infer<typeof storedFileSchema>;

export const resourceSchema = z.object({
  id: z.string(),
  kind: z.enum(["link", "file"]),
  title: z.string(),
  description: z.string().nullable(),
  topic: z.string().nullable(),
  url: z.string().nullable(),
  course: z
    .object({
      id: z.string(),
      version: z.number().int().min(1),
      title: z.string(),
      code: z.string().nullable(),
      archive_status: z.enum(["active", "archived"]),
    })
    .nullable(),
  version: z.number().int().min(1),
  file: storedFileSchema.nullable(),
  created_at: isoDateTime,
  updated_at: isoDateTime,
});

export type Resource = z.infer<typeof resourceSchema>;

const resourceCollectionSchema = z.object({
  data: z.array(resourceSchema),
  meta: z.object({
    pagination: z.object({
      next_cursor: z.string().nullable(),
      previous_cursor: z.string().nullable(),
      per_page: z.number().int(),
    }),
  }),
});

export const uploadGrantSchema = z.object({
  method: z.literal("PUT"),
  url: z.string(),
  headers: z.object({ "Content-Type": z.string() }),
  expires_at: isoDateTime,
});

export type UploadGrant = z.infer<typeof uploadGrantSchema>;

const resourceUploadSchema = z.object({
  resource: resourceSchema,
  upload: uploadGrantSchema,
});

export type ResourceUpload = z.infer<typeof resourceUploadSchema>;

const downloadGrantSchema = z.object({
  url: z.string(),
  expires_at: isoDateTime,
});

export type DownloadGrant = z.infer<typeof downloadGrantSchema>;

export type ResourceListParams = {
  search?: string;
  kind?: "all" | "link" | "file";
  courseId?: string;
  topic?: string;
  fileStatus?: "all" | "pending" | "ready" | "deletion_pending";
  sort?: "updated_at" | "-updated_at";
  perPage?: number;
  cursor?: string;
};

export async function listResources(
  params: ResourceListParams = {},
): Promise<z.infer<typeof resourceCollectionSchema>> {
  const query = toQueryString({
    search: params.search,
    kind: params.kind,
    course_id: params.courseId,
    topic: params.topic,
    file_status: params.fileStatus,
    sort: params.sort,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return resourceCollectionSchema.parse(
    await apiFetch(`/api/v1/resources${query}`),
  );
}

export async function getResource(resourceId: string): Promise<Resource> {
  return resourceSchema.parse(
    envelopeData(await apiFetch(`/api/v1/resources/${resourceId}`)),
  );
}

export type LinkResourceInput = {
  title: string;
  description?: string | null;
  topic?: string | null;
  course_id?: string | null;
  url: string;
};

export async function createLinkResource(
  input: LinkResourceInput,
): Promise<Resource> {
  return resourceSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/resources/links", {
        method: "POST",
        body: input,
      }),
    ),
  );
}

export type FileResourceInput = {
  title: string;
  description?: string | null;
  topic?: string | null;
  course_id?: string | null;
  original_name: string;
  mime_type: AllowedResourceMimeType;
  size: number;
  sha256: string;
};

export async function initiateFileResource(
  input: FileResourceInput,
): Promise<ResourceUpload> {
  return resourceUploadSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/resources/files", {
        method: "POST",
        body: input,
      }),
    ),
  );
}

export type ResourceUpdateInput =
  | {
      kind: "link";
      expected_version: number;
      title: string;
      description?: string | null;
      topic?: string | null;
      course_id?: string | null;
      url: string;
    }
  | {
      kind: "file";
      expected_version: number;
      title: string;
      description?: string | null;
      topic?: string | null;
      course_id?: string | null;
    };

export async function updateResource(
  resourceId: string,
  input: ResourceUpdateInput,
): Promise<Resource> {
  return resourceSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/resources/${resourceId}`, {
        method: "PUT",
        body: input,
      }),
    ),
  );
}

/** Links delete immediately (204); files return 202 while cleanup runs. */
export async function deleteResource(
  resourceId: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/resources/${resourceId}`, {
    method: "DELETE",
    body: { expected_version: expectedVersion },
  });
}

export async function retryResourceUpload(
  resourceId: string,
  expectedVersion: number,
): Promise<ResourceUpload> {
  return resourceUploadSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/resources/${resourceId}/upload-url`, {
        method: "POST",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}

export async function confirmResourceUpload(
  resourceId: string,
  expectedVersion: number,
): Promise<Resource> {
  return resourceSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/resources/${resourceId}/confirm`, {
        method: "POST",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}

export async function cancelResourceUpload(
  resourceId: string,
  expectedVersion: number,
): Promise<void> {
  await apiFetch(`/api/v1/resources/${resourceId}/cancel`, {
    method: "POST",
    body: { expected_version: expectedVersion },
  });
}

/** The returned provider URL is short-lived and must never be logged. */
export async function createResourceDownload(
  resourceId: string,
): Promise<DownloadGrant> {
  return downloadGrantSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/resources/${resourceId}/download`, {
        method: "POST",
      }),
    ),
  );
}

/** Lower-case hex SHA-256 of the file, as the initiate contract requires. */
export async function sha256Hex(file: Blob): Promise<string> {
  const digest = await crypto.subtle.digest(
    "SHA-256",
    await file.arrayBuffer(),
  );

  return Array.from(new Uint8Array(digest))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

export class UploadTransportError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "UploadTransportError";
  }
}

/**
 * Raw signature-bound PUT of the exact declared blob to the grant URL.
 * XMLHttpRequest is used because fetch cannot report upload progress.
 * No credentials or extra headers beyond the grant's are ever attached.
 */
export function putToUploadGrant(
  grant: UploadGrant,
  file: Blob,
  options: { onProgress?: (percent: number) => void; signal?: AbortSignal },
): Promise<void> {
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();

    request.open(grant.method, grant.url);
    request.setRequestHeader("Content-Type", grant.headers["Content-Type"]);

    request.upload.onprogress = (event) => {
      if (event.lengthComputable && options.onProgress) {
        options.onProgress(Math.round((event.loaded / event.total) * 100));
      }
    };

    request.onload = () => {
      if (request.status >= 200 && request.status < 300) {
        resolve();
      } else {
        reject(
          new UploadTransportError(
            `The storage provider rejected the upload (HTTP ${request.status}).`,
          ),
        );
      }
    };
    request.onerror = () => {
      reject(
        new UploadTransportError(
          "The upload could not reach the storage provider.",
        ),
      );
    };
    request.onabort = () => {
      reject(new UploadTransportError("The upload was cancelled."));
    };

    if (options.signal) {
      if (options.signal.aborted) {
        request.abort();
        return;
      }

      options.signal.addEventListener("abort", () => request.abort(), {
        once: true,
      });
    }

    request.send(file);
  });
}
