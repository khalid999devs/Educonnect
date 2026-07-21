"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Skeleton,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { Archive, ArchiveRestore, Library, Pencil, Plus } from "lucide-react";
import { useMemo, useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import {
  archiveCourse,
  createCourse,
  listCourses,
  restoreCourse,
  updateCourse,
  type Course,
} from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import { courseKeys, resourceKeys, settingsKeys } from "@/lib/query-keys";

/**
 * Course preferences: the only course-management surface in the app.
 *
 * Create, rename, archive, and restore only. Deletion is deliberately not
 * offered: DeleteCourseAction refuses whenever a task, focus session, or
 * resource references the course, so a delete button would mostly produce
 * errors and would threaten history the student still needs. Archiving is the
 * honest equivalent and the UI copy says so.
 *
 * Everything here is inline. No dialogs.
 */
const LIST_PARAMS = { status: "all" } as const;

export function CoursePreferencesSection() {
  const queryClient = useQueryClient();
  const [creating, setCreating] = useState(false);
  const [renamingId, setRenamingId] = useState<string | null>(null);
  const [archivingId, setArchivingId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const coursesQuery = useInfiniteQuery({
    queryKey: courseKeys.list(LIST_PARAMS),
    queryFn: ({ pageParam }) =>
      listCourses({ status: "all", perPage: 50, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) =>
      lastPage.meta.pagination.next_cursor ?? undefined,
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: courseKeys.all });
    /* The settings snapshot carries the active/archived counts. */
    void queryClient.invalidateQueries({ queryKey: settingsKeys.all });
    /* Directory listings group resources by course status. */
    void queryClient.invalidateQueries({ queryKey: resourceKeys.all });
  };

  const createMutation = useMutation({
    mutationFn: (input: { title: string; code: string | null }) =>
      createCourse({ title: input.title, code: input.code }),
    onSuccess: (course) => {
      invalidate();
      setCreating(false);
      setNotice(`${course.title} was added to your courses.`);
    },
  });

  const renameMutation = useMutation({
    mutationFn: (input: {
      course: Course;
      title: string;
      code: string | null;
    }) =>
      updateCourse(input.course.id, {
        expected_version: input.course.version,
        title: input.title,
        code: input.code,
        description: input.course.description,
        term_id: input.course.term?.id ?? null,
      }),
    onSuccess: (course) => {
      invalidate();
      setRenamingId(null);
      setNotice(`${course.title} was renamed.`);
    },
  });

  const archiveMutation = useMutation({
    mutationFn: (course: Course) => archiveCourse(course.id, course.version),
    onSuccess: (course) => {
      invalidate();
      setArchivingId(null);
      setNotice(
        `${course.title} is archived. Its tasks, sessions, and resources are untouched.`,
      );
    },
  });

  const restoreMutation = useMutation({
    mutationFn: (course: Course) => restoreCourse(course.id, course.version),
    onSuccess: (course) => {
      invalidate();
      setNotice(`${course.title} is active again.`);
    },
  });

  const courses = useMemo(
    () => coursesQuery.data?.pages.flatMap((page) => page.data) ?? [],
    [coursesQuery.data],
  );
  const active = courses.filter((course) => course.status === "active");
  const archived = courses.filter((course) => course.status === "archived");

  const mutationError = [
    createMutation.error,
    renameMutation.error,
    archiveMutation.error,
    restoreMutation.error,
  ].find((error): error is ApiError => error instanceof ApiError);

  const rowMutating =
    renameMutation.isPending ||
    archiveMutation.isPending ||
    restoreMutation.isPending;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="mb-4">
          <div className="flex items-start gap-3">
            <IconChip icon={Library} accent="settings" size="lg" bordered />
            <div className="space-y-1">
              <CardTitle as="h2">Course preferences</CardTitle>
              <p className="text-body text-text-secondary">
                Courses are the folders everything files into: tasks, focus
                sessions, resources, and captures. This is where you manage
                them.
              </p>
            </div>
          </div>
        </CardHeader>

        <CardContent className="space-y-4">
          <Alert variant="info" title="Courses are archived, never deleted">
            Once a course has a task, a focus session, or a resource attached,
            deleting it would take that history with it. Archiving hides the
            course from pickers and keeps everything filed under it intact. You
            can restore an archived course at any time.
          </Alert>

          {notice ? (
            <Alert variant="success" title="Courses updated">
              {notice}
            </Alert>
          ) : null}

          {mutationError ? (
            <Alert variant="error" title="That change was not saved">
              {mutationError.status === 409
                ? "This course changed in another tab. Reload the list and try again."
                : mutationError.message}
            </Alert>
          ) : null}

          {creating ? (
            <CourseForm
              submitLabel="Add course"
              busy={createMutation.isPending}
              error={
                createMutation.error instanceof ApiError
                  ? createMutation.error
                  : null
              }
              onCancel={() => {
                setCreating(false);
                createMutation.reset();
              }}
              onSubmit={(values) => {
                setNotice(null);
                createMutation.mutate(values);
              }}
            />
          ) : (
            <Button
              onClick={() => {
                setNotice(null);
                setCreating(true);
              }}
            >
              <Plus aria-hidden="true" className="size-4" />
              New course
            </Button>
          )}
        </CardContent>
      </Card>

      {coursesQuery.isPending ? (
        <Card>
          <div className="space-y-2.5">
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </div>
        </Card>
      ) : coursesQuery.isError ? (
        <ErrorState
          title="Courses could not be loaded"
          description="Nothing was changed. Try the request again."
          onRetry={() => void coursesQuery.refetch()}
        />
      ) : courses.length === 0 ? (
        <EmptyState
          icon={Library}
          title="No courses yet"
          description="Add your first course and every capture, task, and resource gets somewhere sensible to live."
          action={
            <Button onClick={() => setCreating(true)}>
              <Plus aria-hidden="true" className="size-4" />
              New course
            </Button>
          }
        />
      ) : (
        <>
          <CourseGroup
            title="Active"
            count={active.length}
            courses={active}
            emptyLabel="Every course is archived right now."
            renamingId={renamingId}
            archivingId={archivingId}
            busy={rowMutating}
            renameError={
              renameMutation.error instanceof ApiError
                ? renameMutation.error
                : null
            }
            onRename={(course) => {
              setNotice(null);
              renameMutation.reset();
              setRenamingId(course.id);
            }}
            onRenameCancel={() => {
              setRenamingId(null);
              renameMutation.reset();
            }}
            onRenameSubmit={(course, values) =>
              renameMutation.mutate({ course, ...values })
            }
            onArchiveIntent={(course) => {
              setNotice(null);
              setArchivingId(course.id);
            }}
            onArchiveCancel={() => setArchivingId(null)}
            onArchive={(course) => archiveMutation.mutate(course)}
            onRestore={(course) => restoreMutation.mutate(course)}
          />

          <CourseGroup
            title="Archived"
            count={archived.length}
            courses={archived}
            emptyLabel="Nothing is archived."
            renamingId={renamingId}
            archivingId={archivingId}
            busy={rowMutating}
            renameError={
              renameMutation.error instanceof ApiError
                ? renameMutation.error
                : null
            }
            onRename={(course) => {
              setNotice(null);
              renameMutation.reset();
              setRenamingId(course.id);
            }}
            onRenameCancel={() => {
              setRenamingId(null);
              renameMutation.reset();
            }}
            onRenameSubmit={(course, values) =>
              renameMutation.mutate({ course, ...values })
            }
            onArchiveIntent={(course) => setArchivingId(course.id)}
            onArchiveCancel={() => setArchivingId(null)}
            onArchive={(course) => archiveMutation.mutate(course)}
            onRestore={(course) => {
              setNotice(null);
              restoreMutation.mutate(course);
            }}
          />

          {coursesQuery.hasNextPage ? (
            <Button
              variant="secondary"
              fullWidth
              isLoading={coursesQuery.isFetchingNextPage}
              loadingLabel="Loading courses"
              onClick={() => void coursesQuery.fetchNextPage()}
            >
              Load more courses
            </Button>
          ) : null}
        </>
      )}
    </div>
  );
}

type CourseValues = { title: string; code: string | null };

function CourseForm({
  initial,
  submitLabel,
  busy,
  error,
  onSubmit,
  onCancel,
}: {
  initial?: Course;
  submitLabel: string;
  busy: boolean;
  error: ApiError | null;
  onSubmit: (values: CourseValues) => void;
  onCancel: () => void;
}) {
  const [title, setTitle] = useState(initial?.title ?? "");
  const [code, setCode] = useState(initial?.code ?? "");

  const trimmedTitle = title.trim();

  return (
    <form
      className="space-y-3 rounded-md border border-border-default bg-bg-elevated p-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          title: trimmedTitle,
          code: code.trim() === "" ? null : code.trim(),
        });
      }}
    >
      <div className="grid gap-3 sm:grid-cols-[2fr_1fr]">
        <FormField
          label="Course title"
          required
          error={error?.fieldError("title")}
        >
          {(control) => (
            <Input
              {...control}
              value={title}
              maxLength={160}
              autoFocus
              placeholder="Operating Systems"
              onChange={(event) => setTitle(event.target.value)}
            />
          )}
        </FormField>

        <FormField label="Course code" error={error?.fieldError("code")}>
          {(control) => (
            <Input
              {...control}
              value={code}
              maxLength={32}
              placeholder="CSE-3101"
              onChange={(event) => setCode(event.target.value)}
            />
          )}
        </FormField>
      </div>

      <div className="flex items-center gap-2">
        <Button
          type="submit"
          size="sm"
          disabled={trimmedTitle === ""}
          isLoading={busy}
          loadingLabel="Saving"
        >
          {submitLabel}
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </form>
  );
}

function CourseGroup({
  title,
  count,
  courses,
  emptyLabel,
  renamingId,
  archivingId,
  busy,
  renameError,
  onRename,
  onRenameCancel,
  onRenameSubmit,
  onArchiveIntent,
  onArchiveCancel,
  onArchive,
  onRestore,
}: {
  title: string;
  count: number;
  courses: Course[];
  emptyLabel: string;
  renamingId: string | null;
  archivingId: string | null;
  busy: boolean;
  renameError: ApiError | null;
  onRename: (course: Course) => void;
  onRenameCancel: () => void;
  onRenameSubmit: (course: Course, values: CourseValues) => void;
  onArchiveIntent: (course: Course) => void;
  onArchiveCancel: () => void;
  onArchive: (course: Course) => void;
  onRestore: (course: Course) => void;
}) {
  return (
    <Card>
      <CardHeader className="mb-3">
        <div className="flex items-center justify-between gap-2">
          <CardTitle as="h2">{title}</CardTitle>
          <Badge variant="neutral">
            <span className="tabular-nums">{count}</span>
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="space-y-2.5">
        {courses.length === 0 ? (
          <p className="text-body text-text-secondary">{emptyLabel}</p>
        ) : (
          courses.map((course) =>
            renamingId === course.id ? (
              <CourseForm
                key={course.id}
                initial={course}
                submitLabel="Save changes"
                busy={busy}
                error={renameError}
                onCancel={onRenameCancel}
                onSubmit={(values) => onRenameSubmit(course, values)}
              />
            ) : (
              <div
                key={course.id}
                className="rounded-md border border-border-subtle px-3.5 py-3 transition-colors hover:border-border-strong"
              >
                <div className="flex flex-wrap items-center gap-3">
                  <IconChip icon={Library} accent="settings" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-body font-medium text-text-primary">
                      {course.title}
                    </p>
                    <p className="truncate text-caption text-text-muted">
                      {course.code ?? "No course code"}
                      {course.term ? ` · ${course.term.label}` : ""}
                    </p>
                  </div>

                  {course.status === "archived" ? (
                    <Badge variant="neutral">Archived</Badge>
                  ) : null}

                  <div className="flex items-center gap-1.5">
                    <Button
                      size="sm"
                      variant="ghost"
                      disabled={busy}
                      onClick={() => onRename(course)}
                    >
                      <Pencil aria-hidden="true" className="size-4" />
                      Rename
                    </Button>

                    {course.status === "active" ? (
                      <Button
                        size="sm"
                        variant="ghost"
                        disabled={busy}
                        onClick={() => onArchiveIntent(course)}
                      >
                        <Archive aria-hidden="true" className="size-4" />
                        Archive
                      </Button>
                    ) : (
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={busy}
                        onClick={() => onRestore(course)}
                      >
                        <ArchiveRestore aria-hidden="true" className="size-4" />
                        Restore
                      </Button>
                    )}
                  </div>
                </div>

                {archivingId === course.id ? (
                  <div className="mt-3 space-y-2.5 rounded-md border border-border-default bg-bg-subtle p-3.5">
                    <p className="text-body text-text-secondary">
                      Archive {course.title}? It disappears from course pickers.
                      Its tasks, focus sessions, and resources stay exactly
                      where they are, and you can restore it later.
                    </p>
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        isLoading={busy}
                        loadingLabel="Archiving"
                        onClick={() => onArchive(course)}
                      >
                        Archive course
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={onArchiveCancel}
                      >
                        Keep active
                      </Button>
                    </div>
                  </div>
                ) : null}
              </div>
            ),
          )
        )}
      </CardContent>
    </Card>
  );
}
