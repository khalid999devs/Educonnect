"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  Dialog,
  ErrorState,
  FormField,
  Input,
  Select,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ArrowLeft, BadgeCheck } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

import { listCourses } from "@/lib/api/courses";
import { ApiError } from "@/lib/api/http";
import { createMentorRequest, getMentor } from "@/lib/api/mentors";
import { courseKeys, mentorKeys } from "@/lib/query-keys";

function messageFrom(error: unknown): string {
  return error instanceof ApiError
    ? error.message
    : "Something went wrong. Please try again.";
}

export function MentorDetail({ mentorId }: { mentorId: string }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [courseId, setCourseId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  const mentorQuery = useQuery({
    queryKey: mentorKeys.mentor(mentorId),
    queryFn: () => getMentor(mentorId),
  });
  const mentor = mentorQuery.data;

  const coursesQuery = useQuery({
    queryKey: courseKeys.list(),
    queryFn: () => listCourses(),
    enabled: dialogOpen,
  });
  const courses = coursesQuery.data?.data ?? [];

  const requestMutation = useMutation({
    mutationFn: () =>
      createMentorRequest(mentorId, {
        subject: subject.trim(),
        message: message.trim(),
        context_course_id: courseId === "" ? null : courseId,
      }),
    onMutate: () => setError(null),
    onSuccess: () => {
      setDialogOpen(false);
      setSent(true);
      setSubject("");
      setMessage("");
      setCourseId("");
    },
    onError: (mutationError) => setError(messageFrom(mutationError)),
  });

  if (mentorQuery.isError) {
    return (
      <div className="mx-auto w-full max-w-3xl">
        <ErrorState
          title="Mentor not found"
          description="This mentor profile may no longer be available."
          onRetry={() => void mentorQuery.refetch()}
        />
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-5">
      <Link
        href="/mentors"
        className="inline-flex items-center gap-1.5 text-caption font-medium text-text-secondary hover:text-brand-primary"
      >
        <ArrowLeft className="size-4" aria-hidden /> Back to mentors
      </Link>

      {sent ? (
        <Alert variant="success" title="Your request has been sent">
          The mentor will see your request and can respond in their own time.
        </Alert>
      ) : null}

      {mentorQuery.isPending || mentor === undefined ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : (
        <Card>
          <CardContent className="flex flex-col gap-4 p-6">
            <div className="flex items-start gap-4">
              <span
                aria-hidden
                className="flex size-14 shrink-0 items-center justify-center rounded-full bg-bg-interactive text-h4 font-semibold text-text-secondary"
              >
                {mentor.name.trim().charAt(0).toUpperCase() || "?"}
              </span>
              <div>
                <h1 className="flex items-center gap-2 text-h3 text-text-primary">
                  {mentor.name}
                  {mentor.verification_state === "verified" ? (
                    <Badge variant="success">
                      <BadgeCheck className="size-3.5" aria-hidden />
                      Verified
                    </Badge>
                  ) : null}
                </h1>
                <p className="text-body-lg text-text-secondary">
                  {mentor.headline}
                </p>
              </div>
            </div>

            <p className="whitespace-pre-wrap break-words text-body text-text-secondary">
              {mentor.bio}
            </p>

            {mentor.expertise.length > 0 ? (
              <div>
                <h2 className="mb-1 text-label font-medium text-text-muted">
                  Expertise
                </h2>
                <ul className="flex flex-wrap gap-1.5">
                  {mentor.expertise.map((tag) => (
                    <li key={tag}>
                      <Badge variant="neutral">{tag}</Badge>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}

            {mentor.availability_note ? (
              <p className="text-caption text-text-muted">
                {mentor.availability_note}
              </p>
            ) : null}

            <div className="pt-1">
              {mentor.is_accepting_requests ? (
                <Button
                  variant="primary"
                  glow
                  onClick={() => setDialogOpen(true)}
                >
                  Request help
                </Button>
              ) : (
                <p className="text-body text-text-muted">
                  This mentor isn&apos;t accepting new requests right now.
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      <Dialog
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
        title={`Ask ${mentor?.name ?? "this mentor"} for help`}
        description="Share what you need help with. This is a one-off request, not a booking."
        footer={
          <>
            <Button variant="ghost" onClick={() => setDialogOpen(false)}>
              Cancel
            </Button>
            <Button
              variant="primary"
              isLoading={requestMutation.isPending}
              loadingLabel="Sending"
              disabled={
                subject.trim().length === 0 || message.trim().length === 0
              }
              onClick={() => requestMutation.mutate()}
            >
              Send request
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          {error ? (
            <Alert variant="error" title="Could not send request">
              {error}
            </Alert>
          ) : null}
          <FormField id="request-subject" label="Subject" required>
            {(control) => (
              <Input
                {...control}
                value={subject}
                maxLength={160}
                onChange={(event) => setSubject(event.target.value)}
                placeholder="e.g. Feedback on my dynamic programming approach"
              />
            )}
          </FormField>
          <FormField id="request-message" label="Message" required>
            {(control) => (
              <Textarea
                {...control}
                value={message}
                rows={4}
                maxLength={2000}
                onChange={(event) => setMessage(event.target.value)}
                placeholder="Describe what you're working on and where you're stuck."
              />
            )}
          </FormField>
          {courses.length > 0 ? (
            <FormField
              id="request-course"
              label="Related course"
              hint="Optional."
            >
              {(control) => (
                <Select
                  {...control}
                  value={courseId}
                  onChange={(event) => setCourseId(event.target.value)}
                >
                  <option value="">No course</option>
                  {courses.map((course) => (
                    <option key={course.id} value={course.id}>
                      {course.title}
                    </option>
                  ))}
                </Select>
              )}
            </FormField>
          ) : null}
        </div>
      </Dialog>
    </div>
  );
}
