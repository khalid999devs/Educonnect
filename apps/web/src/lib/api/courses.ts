import { z } from "zod";

import { apiFetch, toQueryString } from "./http";

/** Course summaries for planner/resource pickers (openapi.yaml Course). */

export const courseSchema = z.object({
  id: z.string(),
  version: z.number().int().min(1),
  title: z.string(),
  code: z.string().nullable(),
  description: z.string().nullable(),
  term: z
    .object({
      id: z.string(),
      version: z.number().int().min(1),
      label: z.string(),
      starts_on: z.string().nullable(),
      ends_on: z.string().nullable(),
    })
    .nullable(),
  status: z.enum(["active", "archived"]),
  archived_at: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
});

export type Course = z.infer<typeof courseSchema>;

const courseCollectionSchema = z.object({
  data: z.array(courseSchema),
  meta: z.object({
    pagination: z.object({
      next_cursor: z.string().nullable(),
      previous_cursor: z.string().nullable(),
      per_page: z.number().int(),
    }),
  }),
});

export async function listCourses(
  params: {
    status?: "active" | "archived" | "all";
    perPage?: number;
    cursor?: string;
  } = {},
): Promise<z.infer<typeof courseCollectionSchema>> {
  const query = toQueryString({
    status: params.status,
    per_page: params.perPage,
    cursor: params.cursor,
  });

  return courseCollectionSchema.parse(
    await apiFetch(`/api/v1/courses${query}`),
  );
}
