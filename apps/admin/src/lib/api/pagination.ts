import { z } from "zod";

import { envelopeData } from "./http";

export type Page<T> = {
  items: T[];
  nextCursor: string | null;
};

/** Parse a cursor-paginated collection envelope into items + the next cursor. */
export function parsePage<T>(payload: unknown, schema: z.ZodType<T>): Page<T> {
  const envelope = payload as {
    data?: unknown;
    meta?: { pagination?: { next_cursor?: string | null } };
  };

  return {
    items: z.array(schema).parse(envelope?.data ?? []),
    nextCursor: envelope?.meta?.pagination?.next_cursor ?? null,
  };
}

/** Parse a single-resource success envelope with the given schema. */
export function parseResource<T>(payload: unknown, schema: z.ZodType<T>): T {
  return schema.parse(envelopeData(payload));
}
