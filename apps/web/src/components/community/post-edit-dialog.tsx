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

import type { Post } from "@/lib/api/community";

export function PostEditDialog({
  post,
  isSubmitting,
  error,
  onClose,
  onSubmit,
}: {
  post: Post | null;
  isSubmitting: boolean;
  error: string | null;
  onClose: () => void;
  onSubmit: (input: { title: string | null; body: string }) => void;
}) {
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");

  useEffect(() => {
    if (post) {
      setTitle(post.title ?? "");
      setBody(post.body ?? "");
    }
  }, [post]);

  return (
    <Dialog
      open={post !== null}
      onClose={onClose}
      title="Edit post"
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button
            variant="primary"
            isLoading={isSubmitting}
            loadingLabel="Saving"
            disabled={body.trim().length === 0}
            onClick={() =>
              onSubmit({
                title: title.trim() === "" ? null : title.trim(),
                body: body.trim(),
              })
            }
          >
            Save changes
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error ? (
          <Alert variant="error" title="Could not save">
            {error}
          </Alert>
        ) : null}
        <FormField id="edit-title" label="Title" hint="Optional.">
          {(control) => (
            <Input
              {...control}
              value={title}
              maxLength={160}
              onChange={(event) => setTitle(event.target.value)}
            />
          )}
        </FormField>
        <FormField id="edit-body" label="Body" required>
          {(control) => (
            <Textarea
              {...control}
              value={body}
              rows={4}
              maxLength={5000}
              onChange={(event) => setBody(event.target.value)}
            />
          )}
        </FormField>
      </div>
    </Dialog>
  );
}
