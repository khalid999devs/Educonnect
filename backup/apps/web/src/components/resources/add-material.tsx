"use client";

import {
  Alert,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  FormField,
  Input,
  Select,
  UploadDropzone,
} from "@educonnect/ui";
import { FilePlus2, Link2, Lock, UploadCloud } from "lucide-react";
import { useState, type FormEvent } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { ApiError } from "@/lib/api/http";
import {
  RESOURCE_FILE_ACCEPT,
  RESOURCE_FILE_ACCEPT_DESCRIPTION,
  type LinkResourceInput,
} from "@/lib/api/resources";
import {
  ARCHIVED_DIRECTORY_REASON,
  type DirectoryCourse,
} from "./directory-card";
import { validateResourceFile } from "./use-uploads";

type Mode = "file" | "link";

export type AddMaterialProps = {
  /** Every course directory, archived ones included: they render disabled
   * with a reason rather than 409ing after the upload starts. */
  courses: DirectoryCourse[];
  /** The open directory's course, or null for the unfiled bucket. New
   * material defaults here. */
  directoryCourseId: string | null;
  directoryName: string;
  onUploadFiles: (
    files: File[],
    meta: { courseId: string | null; topic: string | null },
  ) => void;
  onCreateLink: (input: LinkResourceInput) => void;
  linkBusy: boolean;
  linkError: ApiError | Error | null;
  /** Bumps every time a link is saved so the form can clear. */
  linkSavedCount: number;
};

/** The directory's entry point: a private file upload or an HTTPS link, both
 * landing in the same lifecycle-honest list. New material defaults to the open
 * directory; archived courses are offered but disabled, with the reason shown
 * up front rather than as a 409 after the fact. */
export function AddMaterial({
  courses,
  directoryCourseId,
  directoryName,
  onUploadFiles,
  onCreateLink,
  linkBusy,
  linkError,
  linkSavedCount,
}: AddMaterialProps) {
  const [mode, setMode] = useState<Mode>("file");
  const [courseId, setCourseId] = useState(directoryCourseId ?? "");
  const [topic, setTopic] = useState("");
  const [rejection, setRejection] = useState<string | null>(null);

  const [linkTitle, setLinkTitle] = useState("");
  const [linkUrl, setLinkUrl] = useState("");
  const [savedCountSeen, setSavedCountSeen] = useState(linkSavedCount);

  if (savedCountSeen !== linkSavedCount) {
    setSavedCountSeen(linkSavedCount);
    setLinkTitle("");
    setLinkUrl("");
  }

  const apiError = linkError instanceof ApiError ? linkError : null;
  const archivedCourseCount = courses.filter(
    (course) => course.archive_status === "archived",
  ).length;

  const acceptFiles = (files: File[]) => {
    setRejection(null);

    const accepted: File[] = [];

    for (const file of files) {
      const invalid = validateResourceFile(file);

      if (invalid) {
        setRejection(`${file.name}: ${invalid.message}`);
      } else {
        accepted.push(file);
      }
    }

    if (accepted.length > 0) {
      onUploadFiles(accepted, {
        courseId: courseId === "" ? null : courseId,
        topic: topic.trim() === "" ? null : topic.trim(),
      });
    }
  };

  const submitLink = (event: FormEvent) => {
    event.preventDefault();
    onCreateLink({
      title: linkTitle.trim(),
      url: linkUrl.trim(),
      topic: topic.trim() === "" ? null : topic.trim(),
      course_id: courseId === "" ? null : courseId,
    });
  };

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2.5">
          <IconChip icon={FilePlus2} accent="resources" />
          Add to {directoryName}
        </CardTitle>
        <div
          role="group"
          aria-label="Material kind"
          className="flex items-center rounded-md border border-border-default p-0.5"
        >
          <Button
            variant={mode === "file" ? "secondary" : "ghost"}
            size="sm"
            aria-pressed={mode === "file"}
            onClick={() => setMode("file")}
          >
            <UploadCloud aria-hidden="true" className="size-4" />
            <span className="ml-1.5">Upload file</span>
          </Button>
          <Button
            variant={mode === "link" ? "secondary" : "ghost"}
            size="sm"
            aria-pressed={mode === "link"}
            onClick={() => setMode("link")}
          >
            <Link2 aria-hidden="true" className="size-4" />
            <span className="ml-1.5">Paste link</span>
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            label="Directory"
            hint={
              archivedCourseCount > 0
                ? "Defaults to the open directory. Archived courses cannot take new material."
                : "Defaults to the open directory"
            }
          >
            {(control) => (
              <Select
                {...control}
                value={courseId}
                onChange={(event) => setCourseId(event.target.value)}
              >
                <option value="">Unfiled</option>
                {courses.map((course) => (
                  <option
                    key={course.id}
                    value={course.id}
                    disabled={course.archive_status === "archived"}
                  >
                    {course.code ? `${course.code} · ` : ""}
                    {course.title}
                    {course.archive_status === "archived"
                      ? " (archived, read only)"
                      : ""}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
          <FormField label="Topic" hint="Optional collection name">
            {(control) => (
              <Input
                {...control}
                value={topic}
                maxLength={120}
                onChange={(event) => setTopic(event.target.value)}
                placeholder="e.g. Algorithms"
              />
            )}
          </FormField>
        </div>

        {archivedCourseCount > 0 ? (
          <p className="flex items-start gap-1.5 text-caption text-text-muted">
            <Lock aria-hidden="true" className="mt-0.5 size-3 shrink-0" />
            {ARCHIVED_DIRECTORY_REASON}
          </p>
        ) : null}

        {mode === "file" ? (
          <div className="space-y-3">
            {rejection ? (
              <Alert variant="error" title="That file cannot be uploaded">
                {rejection}
              </Alert>
            ) : null}
            <UploadDropzone
              status="idle"
              multiple
              accept={RESOURCE_FILE_ACCEPT}
              onFilesSelected={acceptFiles}
              acceptDescription={RESOURCE_FILE_ACCEPT_DESCRIPTION}
              maxSizeDescription="Up to 25 MB per file"
              privacyNote="Private to your account; see the privacy policy for handling."
            />
          </div>
        ) : (
          <form onSubmit={submitLink} className="space-y-4">
            {linkError && !apiError ? (
              <Alert variant="error" title="The link could not be saved">
                {linkError.message}
              </Alert>
            ) : null}

            <FormField
              label="Title"
              required
              error={apiError?.fieldError("title")}
            >
              {(control) => (
                <Input
                  {...control}
                  value={linkTitle}
                  maxLength={160}
                  onChange={(event) => setLinkTitle(event.target.value)}
                  placeholder="e.g. Attention is all you need"
                />
              )}
            </FormField>
            <FormField
              label="Link"
              required
              hint="HTTPS links only"
              error={apiError?.fieldError("url")}
            >
              {(control) => (
                <Input
                  {...control}
                  type="url"
                  value={linkUrl}
                  maxLength={2048}
                  onChange={(event) => setLinkUrl(event.target.value)}
                  placeholder="https://…"
                />
              )}
            </FormField>
            <Button
              type="submit"
              isLoading={linkBusy}
              loadingLabel="Saving"
              disabled={linkTitle.trim() === "" || linkUrl.trim() === ""}
              className={cn("w-full sm:w-auto")}
            >
              Save link
            </Button>
          </form>
        )}
      </CardContent>
    </Card>
  );
}
