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
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  "application/vnd.openxmlformats-officedocument.presentationml.presentation",
] as const;

export type AllowedResourceMimeType =
  (typeof ALLOWED_RESOURCE_MIME_TYPES)[number];

/** Mirrors `resources.allowed_mime_types` on the API: the browser file picker
 * filters on both extension and MIME type because some platforms report an empty
 * type for Office files. */
export const RESOURCE_FILE_ACCEPT = [
  ".pdf",
  ".jpg",
  ".jpeg",
  ".png",
  ".webp",
  ".txt",
  ".md",
  ".docx",
  ".pptx",
  ...ALLOWED_RESOURCE_MIME_TYPES,
].join(",");

export const RESOURCE_FILE_ACCEPT_DESCRIPTION =
  "PDF, Word (.docx), PowerPoint (.pptx), JPEG, PNG, WebP, plain text, or Markdown";

const EXTENSION_MIME_TYPES: Record<string, AllowedResourceMimeType> = {
  docx: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  pptx: "application/vnd.openxmlformats-officedocument.presentationml.presentation",
};

/** Resolves the MIME type to declare for a picked file. Browsers report the
 * OOXML types inconsistently - some platforms hand back an empty string or a
 * generic zip type for .docx and .pptx - so the extension is the fallback. The
 * API re-derives and verifies the type server-side either way, so this only
 * decides which files the picker refuses locally. */
export function resolveResourceMimeType(file: {
  name: string;
  type: string;
}): string {
  if ((ALLOWED_RESOURCE_MIME_TYPES as readonly string[]).includes(file.type)) {
    return file.type;
  }

  const extension = file.name.split(".").pop()?.toLowerCase() ?? "";

  return EXTENSION_MIME_TYPES[extension] ?? file.type;
}

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

export const resourceCourseReferenceSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  title: z.string(),
  code: z.string().nullable(),
  archive_status: z.enum(["active", "archived"]),
});

export const resourceSchema = z.object({
  id: z.string(),
  kind: z.enum(["link", "file"]),
  title: z.string(),
  description: z.string().nullable(),
  topic: z.string().nullable(),
  url: z.string().nullable(),
  course: resourceCourseReferenceSchema.nullable(),
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

/** One library bucket: an owned course directory, or the single unfiled bucket. */
export const resourceDirectorySchema = z.object({
  kind: z.enum(["course", "unfiled"]),
  course: resourceCourseReferenceSchema.nullable(),
  resource_count: z.number().int().min(0),
});

export type ResourceDirectory = z.infer<typeof resourceDirectorySchema>;

const resourceDirectoryListSchema = z.array(resourceDirectorySchema);

/** Bounded and uncursored: the set is capped by the owner's course roster. */
export async function listResourceDirectories(): Promise<ResourceDirectory[]> {
  return resourceDirectoryListSchema.parse(
    envelopeData(await apiFetch("/api/v1/resources/directories")),
  );
}

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

/** The sentinel `course_id` value selecting resources filed under no course. */
export const UNFILED_COURSE_ID = "none";

export type ResourceListParams = {
  search?: string;
  kind?: "all" | "link" | "file";
  /** A course public id, or UNFILED_COURSE_ID for the unfiled bucket. */
  courseId?: string;
  topic?: string;
  fileStatus?: "all" | "pending" | "ready" | "deletion_pending";
  sort?: "updated_at" | "-updated_at" | "title" | "-title";
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
