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
import { FilePlus2, Link2, UploadCloud } from "lucide-react";
import { useState, type FormEvent } from "react";

import type { Course } from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import type { LinkResourceInput } from "@/lib/api/resources";
import { validateResourceFile } from "./use-uploads";

type Mode = "file" | "link";

export type AddMaterialProps = {
  courses: Course[];
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

/** The library's single entry point: private file upload or an HTTPS link,
 * both landing in the same lifecycle-honest list. */
export function AddMaterial({
  courses,
  onUploadFiles,
  onCreateLink,
  linkBusy,
  linkError,
  linkSavedCount,
}: AddMaterialProps) {
  const [mode, setMode] = useState<Mode>("file");
  const [courseId, setCourseId] = useState("");
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
        <CardTitle className="flex items-center gap-2">
          <FilePlus2 aria-hidden="true" className="size-5 text-brand-primary" />
          Add material
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
          <FormField label="Course" hint="Applied to what you add next">
            {(control) => (
              <Select
                {...control}
                value={courseId}
                onChange={(event) => setCourseId(event.target.value)}
              >
                <option value="">No course</option>
                {courses.map((course) => (
                  <option key={course.id} value={course.id}>
                    {course.code ? `${course.code} · ` : ""}
                    {course.title}
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
              accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.md,application/pdf,image/jpeg,image/png,image/webp,text/plain,text/markdown"
              onFilesSelected={acceptFiles}
              acceptDescription="PDF, JPEG, PNG, WebP, plain text, or Markdown"
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
