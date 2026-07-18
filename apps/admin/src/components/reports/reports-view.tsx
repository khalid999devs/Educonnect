"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  Dialog,
  ErrorState,
  Select,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { useState } from "react";

import {
  listReports,
  resolveReport,
  type ContentReport,
} from "@/lib/api/admin-reports";
import { ApiError } from "@/lib/api/http";
import { formatDateTime } from "@/lib/format";
import { reportKeys } from "@/lib/query-keys";

const STATUS_BADGE: Record<string, "warning" | "success" | "neutral"> = {
  open: "warning",
  reviewing: "warning",
  actioned: "success",
  dismissed: "neutral",
};

export function ReportsView() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState("open");
  const [target, setTarget] = useState<ContentReport | null>(null);

  const query = useInfiniteQuery({
    queryKey: reportKeys.list(status),
    queryFn: ({ pageParam }) =>
      listReports({ status: status || undefined, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (last) => last.nextCursor ?? undefined,
  });

  const reports = query.data?.pages.flatMap((page) => page.items) ?? [];

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Reports</h1>
        <p className="text-body text-text-secondary">
          Triage reported community content. Every resolution is recorded in the
          audit log; you only see reports within your moderation remit.
        </p>
      </header>

      <div className="w-56">
        <Select
          value={status}
          onChange={(event) => setStatus(event.target.value)}
          aria-label="Filter by status"
        >
          <option value="">All statuses</option>
          <option value="open">Open</option>
          <option value="reviewing">Reviewing</option>
          <option value="actioned">Actioned</option>
          <option value="dismissed">Dismissed</option>
        </Select>
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-28 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load reports"
          description="The moderation queue could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : reports.length === 0 ? (
        <p className="rounded-lg border border-border-subtle bg-bg-surface px-4 py-8 text-center text-body text-text-muted">
          No reports match this filter.
        </p>
      ) : (
        <ul className="space-y-3">
          {reports.map((report) => (
            <li key={report.id}>
              <Card>
                <CardContent className="space-y-3 pt-5">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="flex items-center gap-2">
                      <Badge variant={STATUS_BADGE[report.status] ?? "neutral"}>
                        {report.status}
                      </Badge>
                      <Badge>{report.subject.type}</Badge>
                      <span className="text-caption text-text-muted">
                        {report.reason}
                      </span>
                    </span>
                    <span className="text-caption text-text-muted">
                      {formatDateTime(report.created_at)}
                    </span>
                  </div>
                  {report.community ? (
                    <p className="text-caption text-text-muted">
                      in {report.community.name}
                    </p>
                  ) : null}
                  {/* Reported content is untrusted; React escapes it as inert text. */}
                  {report.subject.excerpt ? (
                    <blockquote className="border-l-2 border-border-default pl-3 text-body text-text-secondary">
                      {report.subject.excerpt}
                    </blockquote>
                  ) : null}
                  {report.detail ? (
                    <p className="text-caption text-text-muted">
                      Reporter note: {report.detail}
                    </p>
                  ) : null}
                  {report.resolution_note ? (
                    <p className="text-caption text-text-secondary">
                      Resolution: {report.resolution_note}
                    </p>
                  ) : null}
                  {report.status === "open" || report.status === "reviewing" ? (
                    <div>
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => setTarget(report)}
                      >
                        Resolve
                      </Button>
                    </div>
                  ) : null}
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {query.hasNextPage ? (
        <div className="flex justify-center">
          <Button
            variant="secondary"
            isLoading={query.isFetchingNextPage}
            onClick={() => void query.fetchNextPage()}
          >
            Load more
          </Button>
        </div>
      ) : null}

      {target ? (
        <ResolveDialog
          report={target}
          onClose={() => setTarget(null)}
          onDone={() => {
            setTarget(null);
            void queryClient.invalidateQueries({ queryKey: reportKeys.all });
          }}
        />
      ) : null}
    </div>
  );
}

function ResolveDialog({
  report,
  onClose,
  onDone,
}: {
  report: ContentReport;
  onClose: () => void;
  onDone: () => void;
}) {
  const [resolution, setResolution] = useState<"actioned" | "dismissed">(
    "actioned",
  );
  const [hideContent, setHideContent] = useState(false);
  const [note, setNote] = useState("");
  const [reason, setReason] = useState("");

  const mutation = useMutation({
    mutationFn: () =>
      resolveReport({
        id: report.id,
        resolution,
        hideContent: resolution === "actioned" ? hideContent : false,
        note: note.trim() || null,
        expectedVersion: report.version,
        reason: reason.trim(),
      }),
    onSuccess: onDone,
  });

  return (
    <Dialog open title="Resolve report" onClose={onClose}>
      <div className="space-y-4">
        {mutation.isError ? (
          <Alert variant="error" title="Could not resolve">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}
        <label className="block">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Decision
          </span>
          <Select
            value={resolution}
            onChange={(event) =>
              setResolution(event.target.value as "actioned" | "dismissed")
            }
          >
            <option value="actioned">Action (uphold the report)</option>
            <option value="dismissed">Dismiss (no violation)</option>
          </Select>
        </label>
        {resolution === "actioned" ? (
          <label className="flex items-center gap-2 text-body">
            <input
              type="checkbox"
              checked={hideContent}
              onChange={(event) => setHideContent(event.target.checked)}
              className="size-4 accent-brand-primary"
            />
            Hide the reported content from members
          </label>
        ) : null}
        <label className="block">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Resolution note (optional, shown to the reporter)
          </span>
          <Textarea
            rows={2}
            value={note}
            onChange={(event) => setNote(event.target.value)}
          />
        </label>
        <label className="block">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Reason (recorded in the audit log)
          </span>
          <Textarea
            rows={2}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            disabled={reason.trim().length === 0}
            onClick={() => mutation.mutate()}
          >
            Resolve
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
