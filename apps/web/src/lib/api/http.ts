/**
 * Typed API boundary for the Laravel backend (doc 07: one client, runtime
 * validation, no ad-hoc fetch). First-party Sanctum SPA flow: cookies with
 * credentials, XSRF header from the XSRF-TOKEN cookie, JSON error envelope
 * ({ error: { code, message, details?, request_id } }) mapped to ApiError.
 */

const API_URL = (
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"
).replace(/\/+$/, "");

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly details: FieldErrors;
  readonly requestId: string | null;

  constructor(
    status: number,
    code: string,
    message: string,
    details: FieldErrors = {},
    requestId: string | null = null,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.details = details;
    this.requestId = requestId;
  }

  /** First message for a field, checking bare and `data.`-prefixed keys. */
  fieldError(field: string): string | undefined {
    return (this.details[field] ?? this.details[`data.${field}`])?.[0];
  }

  static fromPayload(status: number, payload: unknown): ApiError {
    if (
      typeof payload === "object" &&
      payload !== null &&
      "error" in payload &&
      typeof payload.error === "object" &&
      payload.error !== null
    ) {
      const raw = payload.error as Record<string, unknown>;
      const details: FieldErrors = {};
      /* Validation errors nest as error.details.fields; other details are
         used directly. */
      const source =
        typeof raw.details === "object" &&
        raw.details !== null &&
        "fields" in raw.details &&
        typeof (raw.details as { fields: unknown }).fields === "object" &&
        (raw.details as { fields: unknown }).fields !== null
          ? (raw.details as { fields: Record<string, unknown> }).fields
          : raw.details;

      if (typeof source === "object" && source !== null) {
        for (const [key, value] of Object.entries(source)) {
          if (Array.isArray(value)) {
            details[key] = value.filter(
              (item): item is string => typeof item === "string",
            );
          }
        }
      }

      return new ApiError(
        status,
        typeof raw.code === "string" ? raw.code : "UNKNOWN",
        typeof raw.message === "string"
          ? raw.message
          : "The request could not be completed.",
        details,
        typeof raw.request_id === "string" ? raw.request_id : null,
      );
    }

    return new ApiError(
      status,
      "UNKNOWN",
      "The request could not be completed.",
    );
  }
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  for (const part of document.cookie.split("; ")) {
    const [key, ...rest] = part.split("=");

    if (key === name) {
      return rest.join("=");
    }
  }

  return null;
}

async function refreshCsrfCookie(): Promise<void> {
  await fetch(`${API_URL}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

export type ApiFetchOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  signal?: AbortSignal;
};

async function performFetch(
  url: string,
  { method = "GET", body, signal }: ApiFetchOptions,
): Promise<Response> {
  const xsrf = readCookie("XSRF-TOKEN");

  return fetch(url, {
    method,
    credentials: "include",
    signal,
    headers: {
      Accept: "application/json",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(method !== "GET" && xsrf
        ? { "X-XSRF-TOKEN": decodeURIComponent(xsrf) }
        : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
}

/**
 * Fetch an API path (or absolute first-party URL, e.g. the signed
 * verification link). Ensures the CSRF cookie for mutating requests and
 * retries exactly once on a 419 token mismatch.
 */
export async function apiFetch(
  path: string,
  options: ApiFetchOptions = {},
): Promise<unknown> {
  const url = path.startsWith("http") ? path : `${API_URL}${path}`;
  const mutating = (options.method ?? "GET") !== "GET";

  if (mutating && readCookie("XSRF-TOKEN") === null) {
    await refreshCsrfCookie();
  }

  let response = await performFetch(url, options);

  if (response.status === 419 && mutating) {
    await refreshCsrfCookie();
    response = await performFetch(url, options);
  }

  if (response.status === 204) {
    return null;
  }

  const payload: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    throw ApiError.fromPayload(response.status, payload);
  }

  return payload;
}

/** Unwrap the success envelope's `data` member. */
export function envelopeData(payload: unknown): unknown {
  if (typeof payload === "object" && payload !== null && "data" in payload) {
    return (payload as { data: unknown }).data;
  }

  return payload;
}
