import { z } from "zod";

import { apiFetch, envelopeData, toQueryString } from "./http";

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

/** The query-key params every active-course picker shares, so each picker reads
 * the same cache entry instead of one per call site. */
export const ACTIVE_COURSE_LIST_PARAMS = {
  status: "active",
  per_page: 50,
} as const;

const COURSE_PAGE_SIZE = 50;

/**
 * The page ceiling is a safety valve, not a product limit: 20 pages is 1000
 * active courses, which no student roster reaches.
 */
const MAX_COURSE_PAGES = 20;

/**
 * Course pickers must show EVERY active course, so the cursor is followed to
 * exhaustion. `listCourses({ perPage: 50 })` with no cursor silently dropped
 * everything past the first page, in four separate call sites.
 */
export async function fetchAllActiveCourses(): Promise<Course[]> {
  const collected: Course[] = [];
  let cursor: string | undefined;

  for (let page = 0; page < MAX_COURSE_PAGES; page += 1) {
    const response = await listCourses({
      status: "active",
      perPage: COURSE_PAGE_SIZE,
      cursor,
    });

    collected.push(...response.data);

    const next = response.meta.pagination.next_cursor;

    if (next === null) {
      break;
    }

    cursor = next;
  }

  return collected;
}

export type CreateCourseInput = {
  title: string;
  code?: string | null;
  description?: string | null;
  term_id?: string | null;
};

/** Creating a course is how a student creates a new library directory: a
 * resource directory IS a course, so Second Brain's "file it somewhere new"
 * step and Settings' course management write through the same endpoint. */
export async function createCourse(input: CreateCourseInput): Promise<Course> {
  return courseSchema.parse(
    envelopeData(
      await apiFetch("/api/v1/courses", { method: "POST", body: input }),
    ),
  );
}

/**
 * Course rename / archive / restore. Settings is the only course-management
 * surface in the app, so these live here rather than in a feature module.
 *
 * `deleteCourse` is deliberately absent: DeleteCourseAction refuses whenever
 * any task, focus session, or resource references the course, so the button
 * would mostly produce errors. Archiving is the offered path and preserves
 * that history.
 */
export async function updateCourse(
  id: string,
  input: CreateCourseInput & { expected_version: number },
): Promise<Course> {
  return courseSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/courses/${id}`, { method: "PUT", body: input }),
    ),
  );
}

/** Repeating the archive action is a no-op even with a stale version. */
export async function archiveCourse(
  id: string,
  expectedVersion: number,
): Promise<Course> {
  return courseSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/courses/${id}/archive`, {
        method: "PUT",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}

/** Restore is DELETE on the archive sub-resource, not on the course. */
export async function restoreCourse(
  id: string,
  expectedVersion: number,
): Promise<Course> {
  return courseSchema.parse(
    envelopeData(
      await apiFetch(`/api/v1/courses/${id}/archive`, {
        method: "DELETE",
        body: { expected_version: expectedVersion },
      }),
    ),
  );
}
