"use client";

import {
  Alert,
  Button,
  Card,
  CardContent,
  FormField,
  Input,
  Select,
  Textarea,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";

import type { Community, CreatePostInput } from "@/lib/api/community";
import { listResources } from "@/lib/api/resources";
import { resourceKeys } from "@/lib/query-keys";

export function PostComposer({
  communities,
  isSubmitting,
  error,
  onSubmit,
}: {
  communities: Community[];
  isSubmitting: boolean;
  error: string | null;
  onSubmit: (communityId: string, input: CreatePostInput) => void;
}) {
  const [communityId, setCommunityId] = useState(communities[0]?.id ?? "");
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [sharedResourceId, setSharedResourceId] = useState("");

  const linkResources = useQuery({
    queryKey: resourceKeys.list({ kind: "link", perPage: 50 }),
    queryFn: () => listResources({ kind: "link", perPage: 50 }),
  });
  const links = linkResources.data?.data ?? [];

  const selectedCommunity =
    communities.find((community) => community.id === communityId) ??
    communities[0];
  const canSubmit = body.trim().length > 0 && selectedCommunity !== undefined;

  function handleSubmit() {
    if (!canSubmit || selectedCommunity === undefined) {
      return;
    }

    onSubmit(selectedCommunity.id, {
      title: title.trim() === "" ? null : title.trim(),
      body: body.trim(),
      shared_resource_id: sharedResourceId === "" ? null : sharedResourceId,
    });
    setTitle("");
    setBody("");
    setSharedResourceId("");
  }

  return (
    <Card>
      <CardContent className="flex flex-col gap-4 p-5">
        {error ? (
          <Alert variant="error" title="Could not post">
            {error}
          </Alert>
        ) : null}

        {communities.length > 1 ? (
          <FormField id="composer-community" label="Post to">
            {(control) => (
              <Select
                {...control}
                value={selectedCommunity?.id ?? ""}
                onChange={(event) => setCommunityId(event.target.value)}
              >
                {communities.map((community) => (
                  <option key={community.id} value={community.id}>
                    {community.name}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        ) : (
          <p className="text-caption text-text-muted">
            Posting to{" "}
            <span className="font-medium text-text-secondary">
              {selectedCommunity?.name}
            </span>
          </p>
        )}

        <FormField id="composer-title" label="Title" hint="Optional.">
          {(control) => (
            <Input
              {...control}
              value={title}
              maxLength={160}
              onChange={(event) => setTitle(event.target.value)}
              placeholder="Give your post a short title"
            />
          )}
        </FormField>

        <FormField id="composer-body" label="Share an update" required>
          {(control) => (
            <Textarea
              {...control}
              value={body}
              rows={3}
              maxLength={5000}
              onChange={(event) => setBody(event.target.value)}
              placeholder="Ask a question, share a resource, or start a discussion…"
            />
          )}
        </FormField>

        {links.length > 0 ? (
          <FormField
            id="composer-resource"
            label="Attach a saved link"
            hint="Optional: shares one of your link resources."
          >
            {(control) => (
              <Select
                {...control}
                value={sharedResourceId}
                onChange={(event) => setSharedResourceId(event.target.value)}
              >
                <option value="">No attachment</option>
                {links.map((resource) => (
                  <option key={resource.id} value={resource.id}>
                    {resource.title}
                  </option>
                ))}
              </Select>
            )}
          </FormField>
        ) : null}

        <div className="flex justify-end">
          <Button
            variant="primary"
            glow
            isLoading={isSubmitting}
            loadingLabel="Posting"
            disabled={!canSubmit}
            onClick={handleSubmit}
          >
            Post
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
