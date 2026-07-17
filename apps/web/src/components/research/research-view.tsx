"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Dialog,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FlaskConical, Plus } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useState, type FormEvent } from "react";

import { ApiError } from "@/lib/api/http";
import { createResearchTopic, listResearchTopics } from "@/lib/api/research";
import { researchKeys } from "@/lib/query-keys";

export function ResearchView() {
  const queryClient = useQueryClient();
  const [creating, setCreating] = useState(false);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [keywords, setKeywords] = useState("");

  const topicsQuery = useQuery({
    queryKey: researchKeys.list(),
    queryFn: () => listResearchTopics({ perPage: 50 }),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createResearchTopic({
        title: title.trim(),
        description: description.trim() === "" ? null : description.trim(),
        keywords: parseKeywords(keywords),
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: researchKeys.all });
      setCreating(false);
      setTitle("");
      setDescription("");
      setKeywords("");
    },
  });

  const topics = topicsQuery.data?.data ?? [];
  const apiError =
    createMutation.error instanceof ApiError ? createMutation.error : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    createMutation.mutate();
  };

  return (
    <div className="space-y-4">
      <header className="relative min-h-44 overflow-hidden rounded-xl border border-border-default lg:min-h-52">
        <Image
          src="/marketing/minimal-desk.jpg"
          alt=""
          fill
          priority
          sizes="(max-width: 1024px) 100vw, 1100px"
          className="object-cover object-center"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-r from-bg-canvas/95 via-bg-canvas/75 to-bg-canvas/25"
        />
        <div className="relative flex max-w-xl flex-col gap-3 p-6 lg:p-8">
          <h1 className="text-h2 text-text-primary">Research</h1>
          <p className="text-body-lg text-text-secondary">
            Track research topics with keywords and a reading list built from
            your Second Brain — to read, reading, and read.
          </p>
          <div>
            <Button onClick={() => setCreating(true)}>
              <Plus aria-hidden="true" className="size-4" />
              New topic
            </Button>
          </div>
        </div>
      </header>

      {topicsQuery.isError ? (
        <ErrorState
          title="Topics couldn't load"
          onRetry={() => void topicsQuery.refetch()}
        />
      ) : topicsQuery.isPending ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-36 rounded-lg" />
          <Skeleton className="h-36 rounded-lg" />
        </div>
      ) : topics.length === 0 ? (
        <Card>
          <CardContent className="py-10">
            <EmptyState
              icon={FlaskConical}
              title="No research topics yet"
              description="Create a topic to gather sources and track your reading."
              action={
                <Button onClick={() => setCreating(true)}>New topic</Button>
              }
            />
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {topics.map((topic) => (
            <Link key={topic.id} href={`/research/${topic.id}`}>
              <Card className="h-full transition-colors hover:border-border-strong">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2 text-h4">
                    <FlaskConical
                      aria-hidden="true"
                      className="size-5 text-status-research"
                    />
                    {topic.title}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  {topic.description ? (
                    <p className="line-clamp-2 text-body text-text-secondary">
                      {topic.description}
                    </p>
                  ) : null}
                  <div className="flex flex-wrap gap-1.5">
                    {topic.keywords.slice(0, 5).map((keyword) => (
                      <Badge key={keyword} variant="research">
                        {keyword}
                      </Badge>
                    ))}
                  </div>
                  <p className="text-caption text-text-muted">
                    {topic.source_count}{" "}
                    {topic.source_count === 1 ? "source" : "sources"}
                  </p>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}

      <Dialog
        open={creating}
        onClose={
          createMutation.isPending ? () => undefined : () => setCreating(false)
        }
        title="New research topic"
        footer={
          <>
            <Button
              variant="ghost"
              onClick={() => setCreating(false)}
              disabled={createMutation.isPending}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="new-topic-form"
              isLoading={createMutation.isPending}
              loadingLabel="Creating"
              disabled={title.trim() === ""}
            >
              Create topic
            </Button>
          </>
        }
      >
        <form id="new-topic-form" onSubmit={submit} className="space-y-4">
          {createMutation.error && !apiError ? (
            <Alert variant="error" title="Couldn't create the topic">
              {createMutation.error.message}
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
                value={title}
                maxLength={200}
                onChange={(event) => setTitle(event.target.value)}
                placeholder="e.g. Transformer interpretability"
              />
            )}
          </FormField>
          <FormField label="Description">
            {(control) => (
              <Textarea
                {...control}
                value={description}
                maxLength={2000}
                onChange={(event) => setDescription(event.target.value)}
              />
            )}
          </FormField>
          <FormField
            label="Keywords"
            hint="Comma-separated, up to 20"
            error={apiError?.fieldError("keywords")}
          >
            {(control) => (
              <Input
                {...control}
                value={keywords}
                onChange={(event) => setKeywords(event.target.value)}
                placeholder="attention, probing, circuits"
              />
            )}
          </FormField>
        </form>
      </Dialog>
    </div>
  );
}

function parseKeywords(raw: string): string[] {
  return Array.from(
    new Set(
      raw
        .split(",")
        .map((keyword) => keyword.trim())
        .filter((keyword) => keyword !== ""),
    ),
  ).slice(0, 20);
}
