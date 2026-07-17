"use client";

import {
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  EmptyState,
  ErrorState,
  Select,
  Skeleton,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, BookOpen, FlaskConical } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

import {
  getResearchTopic,
  updateResearchSource,
  type ReadingStatus,
} from "@/lib/api/research";
import { researchKeys } from "@/lib/query-keys";

const READING_LABELS: Record<ReadingStatus, string> = {
  to_read: "To read",
  reading: "Reading",
  read: "Read",
};

const READING_VARIANT: Record<
  ReadingStatus,
  "neutral" | "warning" | "success"
> = {
  to_read: "neutral",
  reading: "warning",
  read: "success",
};

export function TopicDetail({ topicId }: { topicId: string }) {
  const queryClient = useQueryClient();
  const [busyItemId, setBusyItemId] = useState<string | null>(null);

  const topicQuery = useQuery({
    queryKey: researchKeys.topic(topicId),
    queryFn: () => getResearchTopic(topicId),
  });

  const statusMutation = useMutation({
    mutationFn: ({
      itemId,
      status,
    }: {
      itemId: string;
      status: ReadingStatus;
    }) => updateResearchSource(topicId, itemId, status),
    onMutate: ({ itemId }) => setBusyItemId(itemId),
    onSettled: () => setBusyItemId(null),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: researchKeys.topic(topicId),
      });
    },
  });

  if (topicQuery.isPending) {
    return <Skeleton className="h-96 rounded-lg" />;
  }

  if (topicQuery.isError) {
    return (
      <ErrorState
        title="This topic couldn't load"
        onRetry={() => void topicQuery.refetch()}
      />
    );
  }

  const topic = topicQuery.data;

  return (
    <div className="space-y-4">
      <Link
        href="/research"
        className="inline-flex items-center gap-1.5 text-body text-brand-primary hover:underline"
      >
        <ArrowLeft aria-hidden="true" className="size-4" />
        Back to research
      </Link>

      <Card>
        <CardHeader className="space-y-2">
          <CardTitle className="flex items-center gap-2 text-h3">
            <FlaskConical
              aria-hidden="true"
              className="size-6 text-status-research"
            />
            {topic.title}
          </CardTitle>
          {topic.description ? (
            <p className="text-body text-text-secondary">{topic.description}</p>
          ) : null}
          {topic.keywords.length > 0 ? (
            <div className="flex flex-wrap gap-1.5">
              {topic.keywords.map((keyword) => (
                <Badge key={keyword} variant="research">
                  {keyword}
                </Badge>
              ))}
            </div>
          ) : null}
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BookOpen
              aria-hidden="true"
              className="size-5 text-brand-primary"
            />
            Reading list
          </CardTitle>
        </CardHeader>
        <CardContent>
          {topic.sources.length === 0 ? (
            <EmptyState
              icon={BookOpen}
              title="No sources yet"
              description="Attach knowledge items from your Second Brain to build this reading list."
            />
          ) : (
            <ul className="space-y-2">
              {topic.sources.map((source) => (
                <li
                  key={source.item.id}
                  className={cn(
                    "flex flex-wrap items-center gap-3 rounded-md border border-border-subtle bg-bg-subtle/60 px-3 py-2.5",
                  )}
                >
                  <div className="min-w-0 flex-1">
                    <Link
                      href={`/second-brain/${source.item.id}`}
                      className="truncate text-body font-medium text-text-primary hover:text-brand-primary hover:underline"
                    >
                      {source.item.title}
                    </Link>
                    {source.item.authors || source.item.published_year ? (
                      <p className="truncate text-caption text-text-muted">
                        {[
                          source.item.authors,
                          source.item.published_year
                            ? String(source.item.published_year)
                            : null,
                        ]
                          .filter(Boolean)
                          .join(" · ")}
                      </p>
                    ) : null}
                  </div>
                  {source.reading_status ? (
                    <Badge variant={READING_VARIANT[source.reading_status]}>
                      {READING_LABELS[source.reading_status]}
                    </Badge>
                  ) : null}
                  <Select
                    value={source.reading_status ?? "to_read"}
                    disabled={busyItemId === source.item.id}
                    onChange={(event) =>
                      statusMutation.mutate({
                        itemId: source.item.id,
                        status: event.target.value as ReadingStatus,
                      })
                    }
                    className="w-36"
                    aria-label={`Reading status for ${source.item.title}`}
                  >
                    <option value="to_read">To read</option>
                    <option value="reading">Reading</option>
                    <option value="read">Read</option>
                  </Select>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
