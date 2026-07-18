"use client";

import {
  Alert,
  Button,
  Dialog,
  FormField,
  Input,
  Textarea,
} from "@educonnect/ui";
import { useEffect, useState } from "react";

import type { MentorProfile, MentorProfileInput } from "@/lib/api/mentors";

function parseExpertise(value: string): string[] {
  const seen = new Set<string>();
  const tags: string[] = [];
  for (const raw of value.split(",")) {
    const tag = raw.trim();
    if (tag !== "" && !seen.has(tag.toLowerCase())) {
      seen.add(tag.toLowerCase());
      tags.push(tag);
    }
  }
  return tags.slice(0, 12);
}

export function MentorProfileForm({
  open,
  initial,
  isSubmitting,
  error,
  onClose,
  onSubmit,
}: {
  open: boolean;
  initial: MentorProfile | null;
  isSubmitting: boolean;
  error: string | null;
  onClose: () => void;
  onSubmit: (input: MentorProfileInput) => void;
}) {
  const [headline, setHeadline] = useState("");
  const [bio, setBio] = useState("");
  const [expertise, setExpertise] = useState("");
  const [availability, setAvailability] = useState("");
  const [accepting, setAccepting] = useState(true);

  useEffect(() => {
    if (open) {
      setHeadline(initial?.headline ?? "");
      setBio(initial?.bio ?? "");
      setExpertise(initial?.expertise.join(", ") ?? "");
      setAvailability(initial?.availability_note ?? "");
      setAccepting(initial?.is_accepting_requests ?? true);
    }
  }, [open, initial]);

  const canSubmit = headline.trim().length > 0 && bio.trim().length > 0;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={initial ? "Edit mentor profile" : "Create your mentor profile"}
      description="Students see this profile in the mentor directory. Keep it honest — no ratings or session counts are shown."
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button
            variant="primary"
            isLoading={isSubmitting}
            loadingLabel="Saving"
            disabled={!canSubmit}
            onClick={() =>
              onSubmit({
                headline: headline.trim(),
                bio: bio.trim(),
                expertise: parseExpertise(expertise),
                availability_note:
                  availability.trim() === "" ? null : availability.trim(),
                is_accepting_requests: accepting,
              })
            }
          >
            {initial ? "Save changes" : "Publish profile"}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error ? (
          <Alert variant="error" title="Could not save profile">
            {error}
          </Alert>
        ) : null}
        <FormField id="mentor-headline" label="Headline" required>
          {(control) => (
            <Input
              {...control}
              value={headline}
              maxLength={160}
              onChange={(event) => setHeadline(event.target.value)}
              placeholder="e.g. Algorithms and study skills mentor"
            />
          )}
        </FormField>
        <FormField id="mentor-bio" label="About you" required>
          {(control) => (
            <Textarea
              {...control}
              value={bio}
              rows={4}
              maxLength={2000}
              onChange={(event) => setBio(event.target.value)}
              placeholder="How you help students, and the areas you know well."
            />
          )}
        </FormField>
        <FormField
          id="mentor-expertise"
          label="Areas of expertise"
          hint="Comma-separated, up to 12."
        >
          {(control) => (
            <Input
              {...control}
              value={expertise}
              onChange={(event) => setExpertise(event.target.value)}
              placeholder="algorithms, study skills, research"
            />
          )}
        </FormField>
        <FormField
          id="mentor-availability"
          label="Availability note"
          hint="Optional — a truthful note, not a guarantee."
        >
          {(control) => (
            <Input
              {...control}
              value={availability}
              maxLength={280}
              onChange={(event) => setAvailability(event.target.value)}
              placeholder="e.g. Usually replies within a few days"
            />
          )}
        </FormField>
        <label className="flex items-center gap-2 text-body text-text-secondary">
          <input
            type="checkbox"
            checked={accepting}
            onChange={(event) => setAccepting(event.target.checked)}
            className="size-4 rounded border-border-default"
          />
          Accepting new help requests
        </label>
      </div>
    </Dialog>
  );
}
