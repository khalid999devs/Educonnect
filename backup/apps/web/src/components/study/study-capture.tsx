"use client";

import {
  Alert,
  Button,
  Card,
  CardContent,
  ErrorState,
  Input,
  Label,
  Skeleton,
  UploadDropzone,
  type UploadStatus,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useCallback, useMemo, useRef, useState } from "react";

import { IntakeDetail } from "@/components/intake/intake-detail";
import { IntakeReview } from "@/components/intake/intake-review";
import { ApiError } from "@/lib/api/http";
import {
  cancelIntakeItem,
  confirmIntake,
  createFileIntake,
  createLinkIntake,
  getIntakeItem,
  isProcessing,
  listIntakeSuggestions,
  retryIntakeItem,
  type ConfirmDecision,
} from "@/lib/api/intake";
import {
  ACTIVE_COURSE_LIST_PARAMS,
  fetchAllActiveCourses,
} from "@/lib/api/courses";
import {
  confirmResourceUpload,
  initiateFileResource,
  MAX_RESOURCE_FILE_BYTES,
  putToUploadGrant,
  resolveResourceMimeType,
  RESOURCE_FILE_ACCEPT,
  RESOURCE_FILE_ACCEPT_DESCRIPTION,
  sha256Hex,
  type AllowedResourceMimeType,
} from "@/lib/api/resources";
import { courseKeys, intakeKeys, resourceKeys } from "@/lib/query-keys";

/**
 * Study's own intake area.
 *
 * Everything happens in this panel: the link box, the drop target, the live
 * pipeline progress, and the review that turns a finished capture into a
 * study-ready library item. There is no dialog at any point.
 *
 * Why a review step exists at all: study generation reads the extracted text
 * through the knowledge item's intake provenance, and that provenance is
 * stamped by the pipeline when a suggestion is confirmed - never claimed by a
 * client. Confirming here is therefore both the product rule ("nothing is
 * created until you confirm") and the only path that produces material a
 * generation can actually read.
 */

type CaptureMode = "link" | "file";

const POLL_INTERVAL_MS = 2500;

export function StudyCapture({
  mode,
  onReady,
  initialIntakeId = null,
}: {
  /** Where the material comes from, owned by the parent so the material step
   * has a single control instead of a toggle within a toggle. */
  mode: CaptureMode;
  /** Called with the knowledge item public id a generation can run against. */
  onReady: (item: { itemId: string; title: string }) => void;
  /** A capture already started elsewhere, adopted so its progress and review
   * render here. The caller remounts this component (via `key`) when it
   * changes, so this is read once as the initial state on purpose. */
  initialIntakeId?: string | null;
}) {
  const queryClient = useQueryClient();
  const [url, setUrl] = useState("");
  const [intakeId, setIntakeId] = useState<string | null>(initialIntakeId);
  const [uploadStatus, setUploadStatus] = useState<UploadStatus>("idle");
  const [uploadProgress, setUploadProgress] = useState<number | undefined>();
  const [uploadName, setUploadName] = useState<string | undefined>();
  const [uploadError, setUploadError] = useState<string | undefined>();
  const [notice, setNotice] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const invalidateIntake = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: intakeKeys.all });
  }, [queryClient]);

  const itemQuery = useQuery({
    queryKey: intakeKeys.item(intakeId ?? ""),
    queryFn: () => getIntakeItem(intakeId as string),
    enabled: intakeId !== null,
    refetchInterval: (query) => {
      const state = query.state.data?.state;

      return state !== undefined && isProcessing(state)
        ? POLL_INTERVAL_MS
        : false;
    },
  });

  const item = itemQuery.data ?? null;
  const awaitingReview = item?.state === "awaiting_review";

  const suggestionsQuery = useQuery({
    queryKey: intakeKeys.suggestions(intakeId ?? ""),
    queryFn: () => listIntakeSuggestions(intakeId as string),
    enabled: intakeId !== null && awaitingReview,
  });

  const coursesQuery = useQuery({
    queryKey: courseKeys.list(ACTIVE_COURSE_LIST_PARAMS),
    queryFn: fetchAllActiveCourses,
    staleTime: 5 * 60_000,
  });

  const linkMutation = useMutation({
    mutationFn: () => createLinkIntake(url.trim()),
    onSuccess: (created) => {
      setIntakeId(created.id);
      setUrl("");
      setNotice("Reading that link now. Progress is below.");
      invalidateIntake();
    },
  });

  const uploadMutation = useMutation({
    mutationFn: async (file: File) => {
      const mimeType = resolveResourceMimeType(file);
      const upload = await initiateFileResource({
        title: file.name.replace(/\.[^.]+$/, "").slice(0, 200) || file.name,
        original_name: file.name,
        mime_type: mimeType as AllowedResourceMimeType,
        size: file.size,
        sha256: await sha256Hex(file),
      });

      const controller = new AbortController();
      abortRef.current = controller;

      await putToUploadGrant(upload.upload, file, {
        signal: controller.signal,
        onProgress: setUploadProgress,
      });

      const resource = await confirmResourceUpload(
        upload.resource.id,
        upload.resource.version,
      );

      return createFileIntake(resource.id);
    },
    onSuccess: (created) => {
      abortRef.current = null;
      setUploadStatus("success");
      setIntakeId(created.id);
      setNotice("Uploaded. Extracting the text now.");
      void queryClient.invalidateQueries({ queryKey: resourceKeys.all });
      invalidateIntake();
    },
    onError: (error: unknown) => {
      abortRef.current = null;
      setUploadStatus("error");
      setUploadError(
        error instanceof ApiError || error instanceof Error
          ? error.message
          : "The upload could not be completed.",
      );
    },
  });

  const pipelineMutation = useMutation({
    mutationFn: ({ action }: { action: "cancel" | "retry" }) =>
      action === "cancel"
        ? cancelIntakeItem(intakeId as string)
        : retryIntakeItem(intakeId as string),
    onSuccess: invalidateIntake,
  });

  const confirmMutation = useMutation({
    mutationFn: (decisions: ConfirmDecision[]) =>
      confirmIntake(intakeId as string, decisions),
    onSuccess: async () => {
      invalidateIntake();
      const saved = await queryClient.fetchQuery({
        queryKey: intakeKeys.suggestions(intakeId as string),
        queryFn: () => listIntakeSuggestions(intakeId as string),
      });
      const knowledge = saved.find(
        (suggestion) =>
          suggestion.kind === "knowledge_item" &&
          suggestion.status === "applied" &&
          suggestion.created_knowledge_item_id !== null,
      );

      if (knowledge?.created_knowledge_item_id) {
        setNotice(null);
        onReady({
          itemId: knowledge.created_knowledge_item_id,
          title: knowledge.proposal.title ?? "Saved material",
        });

        return;
      }

      setNotice(
        "Saved. Nothing from this capture was kept as study material, so there is nothing to generate from yet. Keep the knowledge suggestion next time, or pick something from your library below.",
      );
    },
  });

  const onFilesSelected = (files: File[]) => {
    const file = files[0];

    if (!file) {
      return;
    }

    if (file.size > MAX_RESOURCE_FILE_BYTES) {
      setUploadStatus("error");
      setUploadName(file.name);
      setUploadError("That file is larger than the 25 MB limit.");

      return;
    }

    setUploadName(file.name);
    setUploadError(undefined);
    setUploadProgress(0);
    setUploadStatus("uploading");
    uploadMutation.mutate(file);
  };

  const resetUpload = () => {
    setUploadStatus("idle");
    setUploadProgress(undefined);
    setUploadName(undefined);
    setUploadError(undefined);
  };

  const linkError = linkMutation.error;
  const linkMessage = useMemo(() => {
    if (linkError instanceof ApiError) {
      return linkError.fieldError("url") ?? linkError.message;
    }

    return linkError instanceof Error ? linkError.message : null;
  }, [linkError]);

  const confirmError = confirmMutation.error;

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="space-y-4">
          {mode === "link" ? (
            <form
              className="space-y-2"
              onSubmit={(event) => {
                event.preventDefault();

                if (url.trim() !== "") {
                  linkMutation.mutate();
                }
              }}
            >
              <Label htmlFor="study-capture-url">Link to the material</Label>
              <div className="flex flex-wrap gap-2">
                <Input
                  id="study-capture-url"
                  type="url"
                  inputMode="url"
                  className="min-w-0 flex-1"
                  placeholder="https://example.edu/lecture-notes"
                  value={url}
                  onChange={(event) => setUrl(event.target.value)}
                />
                <Button
                  type="submit"
                  disabled={url.trim() === "" || linkMutation.isPending}
                >
                  {linkMutation.isPending ? "Reading..." : "Bring it in"}
                </Button>
              </div>
              <p className="text-caption text-text-muted">
                Public https links only. The page is fetched once, its text is
                extracted, and nothing is saved until you confirm.
              </p>
              {linkMessage ? (
                <p role="alert" className="text-caption text-status-error">
                  {linkMessage}
                </p>
              ) : null}
            </form>
          ) : (
            <UploadDropzone
              status={uploadStatus}
              progress={uploadProgress}
              fileName={uploadName}
              errorMessage={uploadError}
              accept={RESOURCE_FILE_ACCEPT}
              acceptDescription={RESOURCE_FILE_ACCEPT_DESCRIPTION}
              maxSizeDescription="Up to 25 MB per file"
              privacyNote="Your files stay private to your account and are never used to train a model."
              onFilesSelected={onFilesSelected}
              onCancel={() => {
                abortRef.current?.abort();
                resetUpload();
              }}
              onRetry={resetUpload}
              onReset={resetUpload}
            />
          )}
        </CardContent>
      </Card>

      {notice ? (
        <Alert variant="info" title="Capture">
          {notice}
        </Alert>
      ) : null}

      {intakeId !== null ? (
        itemQuery.isPending ? (
          <Skeleton className="h-40 rounded-lg" />
        ) : itemQuery.isError ? (
          <ErrorState
            title="Progress could not be loaded"
            description="The capture may still be running. Try again."
            onRetry={() => void itemQuery.refetch()}
          />
        ) : item ? (
          <IntakeDetail
            item={item}
            busy={pipelineMutation.isPending}
            onCancel={() => pipelineMutation.mutate({ action: "cancel" })}
            onRetry={() => pipelineMutation.mutate({ action: "retry" })}
          >
            {awaitingReview ? (
              suggestionsQuery.isPending ? (
                <Skeleton className="h-32 rounded-lg" />
              ) : suggestionsQuery.isError ? (
                <ErrorState
                  title="Suggestions could not be loaded"
                  onRetry={() => void suggestionsQuery.refetch()}
                />
              ) : (suggestionsQuery.data ?? []).length === 0 ? (
                <p className="text-body text-text-secondary">
                  Nothing was suggested from this capture, so there is nothing
                  to save.
                </p>
              ) : (
                <IntakeReview
                  suggestions={suggestionsQuery.data ?? []}
                  courses={coursesQuery.data ?? []}
                  busy={confirmMutation.isPending}
                  error={
                    confirmError instanceof Error ? confirmError.message : null
                  }
                  onConfirm={(decisions) => confirmMutation.mutate(decisions)}
                />
              )
            ) : null}
          </IntakeDetail>
        ) : null
      ) : null}
    </div>
  );
}
